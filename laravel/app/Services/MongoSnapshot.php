<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\Client;
use RuntimeException;

class MongoSnapshot
{
    public function verify(string $target): array
    {
        if (! preg_match('/^cmms_rehearsal_[a-z0-9_]+$/', $target)) {
            throw new RuntimeException('Đích phải có tiền tố cmms_rehearsal_.');
        }
        $mongo = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase($target);
        $manifest = $mongo->selectCollection('_migration_manifest')->findOne(['_id' => 'mysql_snapshot'], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        if (! $manifest) {
            throw new RuntimeException('Không có manifest snapshot hoàn tất.');
        }
        foreach ($manifest['report']['tables'] as $table => $expected) {
            $key = collect($manifest['schema'][$table]['indexes'])->firstWhere('primary', true)['columns'][0];
            $collection = $mongo->selectCollection($table);
            $digest = hash_init('sha256');
            foreach ($collection->find([], ['sort' => [$key => 1], 'typeMap' => ['root' => 'array', 'document' => 'object', 'array' => 'array']]) as $document) {
                hash_update($digest, $this->canonical($document)."\n");
            }
            if ($collection->countDocuments() !== $expected['rows'] || ! hash_equals($expected['sha256'], hash_final($digest))) {
                throw new RuntimeException('Snapshot không khớp: '.$table);
            }
        }

        return ['target' => $target, 'verified_tables' => count($manifest['report']['tables']), 'application_ready' => false];
    }

    public function import(string $target): array
    {
        if (! preg_match('/^cmms_rehearsal_[a-z0-9_]+$/', $target)) {
            throw new RuntimeException('Đích phải có tiền tố cmms_rehearsal_.');
        }
        $mongo = (new Client(config('database.connections.mongodb.dsn'), ['serverSelectionTimeoutMS' => 10000]))->selectDatabase($target);
        if (iterator_count($mongo->listCollections()) !== 0) {
            throw new RuntimeException('Database đích phải trống; không ghi đè dữ liệu đã có.');
        }
        $source = DB::connection('mysql');
        $schema = $source->getSchemaBuilder();
        $tables = $schema->getTableListing(schema: $source->getDatabaseName(), schemaQualified: false);
        sort($tables);
        $definitions = [];
        foreach ($tables as $table) {
            $definitions[$table] = ['columns' => $schema->getColumns($table), 'indexes' => $schema->getIndexes($table), 'foreign_keys' => $schema->getForeignKeys($table)];
        }
        $report = ['source' => $source->getDatabaseName(), 'target' => $target, 'application_ready' => false, 'tables' => [], 'foreign_keys_checked' => 0];
        $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $source->transaction(function () use ($source, $mongo, $definitions, &$report): void {
            foreach ($definitions as $table => $definition) {
                $columns = array_column($definition['columns'], null, 'name');
                $primary = collect($definition['indexes'])->firstWhere('primary', true)['columns'] ?? [];
                if (count($primary) !== 1) {
                    throw new RuntimeException('Cần ánh xạ khóa chính riêng cho '.$table);
                }
                $key = $primary[0];
                $properties = ['_id' => ['bsonType' => $this->bsonType($columns[$key]['type_name'])]];
                $required = ['_id'];
                foreach ($columns as $name => $column) {
                    $type = $this->bsonType($column['type_name']);
                    $properties[$name] = ['bsonType' => $column['nullable'] ? array_merge((array) $type, ['null']) : $type];
                    if (! $column['nullable']) {
                        $required[] = $name;
                    }
                }
                $mongo->createCollection($table, ['validator' => ['$jsonSchema' => ['bsonType' => 'object', 'required' => $required, 'properties' => $properties]], 'validationLevel' => 'strict', 'validationAction' => 'error']);
                $collection = $mongo->selectCollection($table);
                foreach ($definition['indexes'] as $index) {
                    if ($index['primary']) {
                        continue;
                    }
                    $options = ['name' => $index['name'], 'unique' => $index['unique']];
                    if ($index['unique']) {
                        $conditions = [];
                        foreach ($index['columns'] as $column) {
                            $conditions[$column] = ['$type' => $this->bsonType($columns[$column]['type_name'])];
                        }
                        $options['partialFilterExpression'] = $conditions;
                        if (collect($index['columns'])->contains(fn ($name) => $this->bsonType($columns[$name]['type_name']) === 'string')) {
                            $options['collation'] = ['locale' => 'vi', 'strength' => 1];
                        }
                    }
                    $collection->createIndex(array_fill_keys($index['columns'], 1), $options);
                }
                $digest = hash_init('sha256');
                $count = 0;
                $rows = $source->table($table);
                if ($this->bsonType($columns[$key]['type_name']) === 'string') {
                    $rows->orderByRaw('BINARY `'.str_replace('`', '``', $key).'`');
                } else {
                    $rows->orderBy($key);
                }
                foreach ($rows->lazy(500)->chunk(500) as $batch) {
                    $documents = [];
                    foreach ($batch as $row) {
                        $document = [];
                        foreach ((array) $row as $name => $value) {
                            $document[$name] = $this->encode($value, $columns[$name]['type_name']);
                        }
                        $document['_id'] = $document[$key];
                        $documents[] = $document;
                        hash_update($digest, $this->canonical($document)."\n");
                        $count++;
                    }
                    $collection->insertMany($documents);
                }
                $expected = hash_final($digest);
                $actual = hash_init('sha256');
                foreach ($collection->find([], ['sort' => [$key => 1], 'typeMap' => ['root' => 'array', 'document' => 'object', 'array' => 'array']]) as $document) {
                    hash_update($actual, $this->canonical($document)."\n");
                }
                if ($collection->countDocuments() !== $count || ! hash_equals($expected, hash_final($actual))) {
                    throw new RuntimeException('Đối soát dữ liệu thất bại: '.$table);
                }
                $report['tables'][$table] = ['rows' => $count, 'sha256' => $expected];
            }
        });
        foreach ($definitions as $table => $definition) {
            foreach ($definition['foreign_keys'] as $foreignKey) {
                $filter = [];
                $conditions = [];
                $variables = [];
                foreach ($foreignKey['columns'] as $index => $column) {
                    $filter[$column] = ['$ne' => null];
                    $variables['key'.$index] = '$'.$column;
                    $conditions[] = ['$eq' => ['$'.$foreignKey['foreign_columns'][$index], '$$key'.$index]];
                }
                $orphans = $mongo->selectCollection($table)->aggregate([
                    ['$match' => $filter],
                    ['$lookup' => ['from' => $foreignKey['foreign_table'], 'let' => $variables, 'pipeline' => [['$match' => ['$expr' => ['$and' => $conditions]]], ['$limit' => 1]], 'as' => '_parent']],
                    ['$match' => ['_parent' => ['$size' => 0]]], ['$limit' => 1],
                ])->toArray();
                if ($orphans !== []) {
                    throw new RuntimeException('Tham chiếu mồ côi: '.$table.'.'.$foreignKey['name']);
                }
                $report['foreign_keys_checked']++;
            }
        }
        $mongo->selectCollection('_migration_manifest')->insertOne(['_id' => 'mysql_snapshot', 'schema' => $definitions, 'report' => $report, 'application_ready' => false]);

        return $report;
    }

    private function bsonType(string $type): string|array
    {
        return match (strtolower($type)) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => ['int', 'long'],
            'decimal', 'numeric' => 'decimal',
            'float', 'double', 'real' => 'double',
            'json' => ['object', 'array'],
            default => 'string',
        };
    }

    private function encode(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array(strtolower($type), ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
            if (bccomp((string) $value, (string) PHP_INT_MAX) > 0 || bccomp((string) $value, (string) PHP_INT_MIN) < 0) {
                throw new RuntimeException('ID vượt phạm vi số nguyên 64 bit. Cần ánh xạ riêng.');
            }

            return (int) $value;
        }

        return match (strtolower($type)) {
            'decimal', 'numeric' => new Decimal128((string) $value),
            'float', 'double', 'real' => (float) $value,
            'json' => json_decode($value, false, 512, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function canonical(mixed $value): string
    {
        return json_encode($this->normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Decimal128) {
            return ['$numberDecimal' => (string) $value];
        }
        if (is_object($value)) {
            return ['$document' => $this->normalize((array) $value)];
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        return $value;
    }
}
