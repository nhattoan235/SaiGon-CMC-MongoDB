<?php

namespace App\Services;

use App\Mongo\DocumentStore;
use Illuminate\Support\Facades\DB;
use LogicException;
use MongoDB\Laravel\Connection;
use RuntimeException;

class MysqlSnapshot
{
    private const ArchiveTables = ['migrations', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private function connection(): Connection
    {
        $connection = DB::connection();
        if (! $connection instanceof Connection) {
            throw new LogicException('Snapshot destination must be MongoDB.');
        }

        return $connection;
    }

    public function inspect(string $directory): array
    {
        $directory = realpath($directory);
        if ($directory === false || ! is_file($directory.'/manifest.json')) {
            throw new RuntimeException('Snapshot manifest not found.');
        }
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 'cmc-mysql-snapshot-v1' || ! is_array($manifest['tables'] ?? null)) {
            throw new RuntimeException('Unsupported snapshot format.');
        }
        foreach ($manifest['tables'] as $table => $metadata) {
            if (! preg_match('/^[a-z0-9_]+$/D', $table)) {
                throw new RuntimeException('Invalid table name in snapshot.');
            }
            $path = $directory.'/'.$table.'.ndjson';
            if (! is_file($path) || ! hash_equals($metadata['sha256'], hash_file('sha256', $path))) {
                throw new RuntimeException('Snapshot checksum failed for '.$table);
            }
            if (! in_array($table, self::ArchiveTables, true)) {
                DocumentStore::schema($table);
            }
            $count = 0;
            foreach ($this->rows($path) as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException('Invalid snapshot row.');
                }
                $count++;
            }
            if ($count !== $metadata['rows']) {
                throw new RuntimeException('Snapshot row count failed for '.$table);
            }
        }

        return ['directory' => $directory, 'manifest' => $manifest, 'identity' => hash_file('sha256', $directory.'/manifest.json')];
    }

    private function rows(string $path): iterable
    {
        $stream = fopen($path, 'rb');
        try {
            while (($line = fgets($stream)) !== false) {
                if (trim($line) !== '') {
                    yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    public function import(string $directory, bool $dryRun = false): array
    {
        $snapshot = $this->inspect($directory);
        $connection = $this->connection();
        $store = new DocumentStore($connection);
        $tables = $snapshot['manifest']['tables'];
        if ($dryRun) {
            foreach ($tables as $table => $metadata) {
                if (in_array($table, self::ArchiveTables, true)) {
                    continue;
                }
                foreach ($this->rows($snapshot['directory'].'/'.$table.'.ndjson') as $row) {
                    $store->encode($table, $row, true);
                }
            }

            return ['dry_run' => true, 'tables' => count($tables), 'rows' => array_sum(array_column($tables, 'rows'))];
        }
        $markers = $connection->getCollection('_snapshot_imports');
        $marker = $markers->findOne(['_id' => $snapshot['identity']]);
        if ($marker === null) {
            if ($markers->countDocuments() !== 0) {
                throw new RuntimeException('This target contains another snapshot; use a new isolated database.');
            }
            foreach (DocumentStore::schema() as $logical => $schema) {
                if (in_array($logical, self::ArchiveTables, true)) {
                    continue;
                }
                $physical = $schema['collection'];
                if ($connection->getCollection($physical)->countDocuments() !== 0) {
                    throw new RuntimeException('Destination is not empty. Refusing to overwrite existing application data.');
                }
            }
            $markers->insertOne(['_id' => $snapshot['identity'], 'state' => 'importing', 'source_commit' => $snapshot['manifest']['source_commit'] ?? null]);
        }
        $order = array_keys($tables);
        usort($order, static function (string $left, string $right): int {
            $isEmbedded = static fn (string $table): bool => ! in_array($table, self::ArchiveTables, true) && isset(DocumentStore::schema($table)['parent']);

            return $isEmbedded($left) <=> $isEmbedded($right);
        });
        $inserted = 0;
        foreach ($order as $table) {
            $archive = in_array($table, self::ArchiveTables, true);
            $position = 0;
            foreach ($this->rows($snapshot['directory'].'/'.$table.'.ndjson') as $row) {
                $position++;
                if ($archive) {
                    $key = $snapshot['identity'].':'.$table.':'.$position;
                    $connection->getCollection('_legacy_runtime_archive')->updateOne(
                        ['_id' => $key],
                        ['$setOnInsert' => ['source_table' => $table, 'source_position' => $position, 'snapshot' => $snapshot['identity'], 'payload' => $row]],
                        ['upsert' => true],
                    );

                    continue;
                }
                $schema = DocumentStore::schema($table);
                $primary = $schema['primary'];
                $id = $row[$primary];
                $existing = DB::table($table)->where($primary, $id)->first();
                if ($existing === null) {
                    $store->insert($table, $row);
                    $inserted++;
                } else {
                    $this->assertRow($table, $row, $existing, $store);
                }
            }
        }
        $verification = $this->verify($directory);
        $markers->updateOne(['_id' => $snapshot['identity']], ['$set' => ['state' => 'verified', 'verified_at' => now()->toIso8601String(), 'verification' => $verification]]);

        return ['inserted' => $inserted] + $verification;
    }

    public function verify(string $directory): array
    {
        $snapshot = $this->inspect($directory);
        $connection = $this->connection();
        $store = new DocumentStore($connection);
        $checked = 0;
        $archived = 0;
        foreach ($snapshot['manifest']['tables'] as $table => $metadata) {
            if (in_array($table, self::ArchiveTables, true)) {
                $count = $connection->getCollection('_legacy_runtime_archive')->countDocuments(['snapshot' => $snapshot['identity'], 'source_table' => $table]);
                if ($count !== $metadata['rows']) {
                    throw new RuntimeException('Archived row count differs for '.$table);
                }
                $position = 0;
                foreach ($this->rows($snapshot['directory'].'/'.$table.'.ndjson') as $row) {
                    $position++;
                    $saved = $connection->getCollection('_legacy_runtime_archive')->findOne(['_id' => $snapshot['identity'].':'.$table.':'.$position], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
                    if ($this->canonical($row) !== $this->canonical($saved['payload'] ?? null)) {
                        throw new RuntimeException('Archived content differs for '.$table);
                    }
                    $archived++;
                }

                continue;
            }
            if (DB::table($table)->count() !== $metadata['rows']) {
                throw new RuntimeException('Destination row count differs for '.$table);
            }
            $schema = DocumentStore::schema($table);
            foreach ($this->rows($snapshot['directory'].'/'.$table.'.ndjson') as $row) {
                $saved = DB::table($table)->where($schema['primary'], $row[$schema['primary']])->first();
                if ($saved === null) {
                    throw new RuntimeException('Destination row missing for '.$table);
                }
                $this->assertRow($table, $row, $saved, $store);
                $checked++;
            }
        }

        return ['verified' => true, 'tables' => count($snapshot['manifest']['tables']), 'business_rows' => $checked, 'archived_runtime_rows' => $archived];
    }

    private function assertRow(string $table, array $source, object $saved, DocumentStore $store): void
    {
        $schema = DocumentStore::schema($table);
        $encoded = $store->encode($table, $source);
        if (isset($schema['parent'])) {
            $encoded[$schema['primary']] = $encoded['item_id'];
        }
        $expected = (array) DocumentStore::normalize($table, $encoded);
        $actual = (array) $saved;
        foreach ($source as $field => $unused) {
            $key = $field === $schema['primary'] ? $schema['primary'] : $field;
            if (! array_key_exists($key, $actual)) {
                throw new RuntimeException('Destination field missing: '.$table.'.'.$field);
            }
            $left = $expected[$key] ?? null;
            $right = $actual[$key];
            if (($schema['fields'][$field]['type'] ?? null) === 'json' && $left !== null && $right !== null) {
                $left = json_decode($left, true, flags: JSON_THROW_ON_ERROR);
                $right = json_decode($right, true, flags: JSON_THROW_ON_ERROR);
            }
            if ($this->canonical($left) !== $this->canonical($right)) {
                throw new RuntimeException('Destination field differs: '.$table.'.'.$field);
            }
        }
    }

    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = json_decode($this->canonical($item), true, flags: JSON_THROW_ON_ERROR);
            }
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
