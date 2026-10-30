<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MongoInstaller
{
    public function install(): void
    {
        $connection = DB::connection('mongodb');
        $existing = iterator_to_array($connection->mongo->listCollectionNames());
        foreach ($connection->definitions as $table => $definition) {
            if (! in_array($table, $existing, true)) {
                $properties = [];
                $required = [];
                foreach ($definition['columns'] as $column) {
                    $type = $this->type($column['type_name']);
                    $properties[$column['name']] = ['bsonType' => $column['nullable'] ? array_merge((array) $type, ['null']) : $type];
                    if (! $column['nullable']) {
                        $required[] = $column['name'];
                    }
                }
                $connection->mongo->createCollection($table, ['validator' => ['$jsonSchema' => ['bsonType' => 'object', 'required' => $required, 'properties' => $properties]], 'validationLevel' => 'strict']);
            }
            $collection = $connection->mongo->selectCollection($table);
            $columns = $connection->columns($table);
            $primary = $connection->key($table);
            $primaryOptions = ['name' => 'cmc_primary', 'unique' => true];
            if ($this->type($columns[$primary]['type_name']) === 'string') {
                $primaryOptions['collation'] = ['locale' => 'vi', 'strength' => 1];
            }
            $collection->createIndex([$primary => 1], $primaryOptions);
            foreach ($definition['indexes'] as $index) {
                if ($index['primary']) {
                    continue;
                }
                $options = ['name' => $index['name'], 'unique' => $index['unique']];
                if ($index['unique']) {
                    $filter = [];
                    foreach ($index['columns'] as $column) {
                        $filter[$column] = ['$type' => $this->type($columns[$column]['type_name'])];
                    }
                    $options['partialFilterExpression'] = $filter;
                    if (collect($index['columns'])->contains(fn ($name) => $this->type($columns[$name]['type_name']) === 'string')) {
                        $options['collation'] = ['locale' => 'vi', 'strength' => 1];
                    }
                }
                $collection->createIndex(array_fill_keys($index['columns'], 1), $options);
            }
            $key = $connection->key($table);
            $max = 0;
            foreach ($collection->find([], ['typeMap' => ['root' => 'array', 'document' => 'object']]) as $row) {
                $id = $row['_id'];
                unset($row['_id'], $row['_search']);
                $encoded = $connection->encode($table, $row);
                $search = array_filter($encoded, fn ($field) => str_starts_with($field, '_search.'), ARRAY_FILTER_USE_KEY);
                if ($search !== []) {
                    $collection->updateOne(['_id' => $id], ['$set' => $search]);
                }
                if (is_int($row[$key])) {
                    $max = max($max, $row[$key]);
                }
            }
            $connection->mongo->selectCollection('_cmc_sequences')->updateOne(['_id' => $table], ['$max' => ['value' => $max]], ['upsert' => true]);
        }
        $connection->mongo->selectCollection('_cmc_locks')->updateOne(['_id' => 'business'], ['$setOnInsert' => ['version' => 0]], ['upsert' => true]);
    }

    public function resetTests(): void
    {
        $connection = DB::connection('mongodb');
        if (! preg_match('/^cmms_(?:mongo_test|qa_)/', $connection->getDatabaseName()) || ! app()->environment('testing')) {
            throw new RuntimeException('Chỉ được xóa dữ liệu trong database kiểm thử MongoDB riêng.');
        }
        foreach (array_keys($connection->definitions) as $table) {
            $connection->mongo->selectCollection($table)->deleteMany([]);
        }
        $connection->mongo->selectCollection('_cmc_sequences')->updateMany([], ['$set' => ['value' => 0]]);
    }

    private function type(string $type): string|array
    {
        return match ($type) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => ['int', 'long'],
            'decimal', 'numeric' => 'decimal', 'float', 'double' => 'double', 'json' => ['object', 'array'],
            default => 'string',
        };
    }
}
