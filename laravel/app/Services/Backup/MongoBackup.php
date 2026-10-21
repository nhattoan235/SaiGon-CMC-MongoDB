<?php

namespace App\Services\Backup;

use App\Mongo\DocumentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use MongoDB\BSON\Document;
use MongoDB\Database;
use RuntimeException;
use Symfony\Component\Process\Process;

class MongoBackup
{
    public function __construct(private BackupWriteGate $gate, private BackupArchive $archive, private BackupRepository $repository) {}

    public function capture(string $destination): array
    {
        if (file_exists($destination)) {
            throw new RuntimeException('Thư mục capture đã tồn tại.');
        }
        File::ensureDirectoryExists($destination, 0700);

        return $this->gate->exclusive(function () use ($destination): array {
            $connection = DB::connection('mongodb');
            $database = $connection->getDatabase();
            $name = $database->getDatabaseName();
            $this->databaseName($name);
            $arguments = ['--db', $name, '--archive='.$destination.'/database.archive.gz', '--gzip'];
            foreach (config('backup.excluded') as $collection) {
                $arguments[] = '--excludeCollection='.$collection;
            }
            $dumpVersion = $this->tool('dump', ['--version']);
            $this->tool('dump', $arguments, true);
            $revision = new Process(['git', 'rev-parse', 'HEAD'], base_path('..'), null, null, 10);
            $revision->run();
            $migration = $database->selectCollection('migrations')->findOne([], ['sort' => ['batch' => -1, 'migration' => -1]]);
            $manifest = ['format' => 1, 'application' => 'saigon-cmc', 'source_database' => $name,
                'commit' => $revision->isSuccessful() ? trim($revision->getOutput()) : null,
                'schema_version' => $migration['migration'] ?? null,
                'instance' => config('backup.instance'), 'capture_at' => now()->toIso8601String(),
                'mongo_version' => $database->command(['buildInfo' => 1])->toArray()[0]['version'],
                'tools_version' => trim(strtok($dumpVersion, "\n")), 'app_version' => app()->version(),
                'excluded' => config('backup.excluded'), 'collections' => $this->describe($database), 'files' => []];
            foreach (config('backup.sources') as $scope => $source) {
                if (! in_array($scope, ['private', 'public'], true) || ! is_dir($source)) {
                    continue;
                }
                $realSource = realpath($source);
                $realRoot = realpath($this->repository->root());
                if ($this->inside($realRoot, $realSource) || $this->inside($realSource, $realRoot) || is_link($source)) {
                    throw new RuntimeException('Nguồn upload phải tách khỏi staging/token backup.');
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    if ($file->isLink() || ! $this->inside($file->getRealPath(), $realSource)) {
                        throw new RuntimeException('Nguồn upload có symlink/junction không an toàn.');
                    }
                    if (! $file->isFile()) {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1);
                    $path = $destination.'/files/'.$scope.'/'.$relative;
                    File::ensureDirectoryExists(dirname($path), 0700);
                    if (! copy($file->getPathname(), $path)) {
                        throw new RuntimeException('Không chụp được tệp upload.');
                    }
                }
            }
            foreach (File::allFiles($destination) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $manifest['files'][$relative] = hash_file('sha256', $file->getPathname());
            }
            $this->repository->writeJson($destination.'/manifest.json', $manifest);

            return $manifest;
        });
    }

    public function verify(string $artifact): array
    {
        $directory = $this->repository->root().'/verify-'.bin2hex(random_bytes(8));
        try {
            return $this->archive->unpackVerified($artifact, $directory);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function restore(string $artifact, string $targetDatabase, string $targetFiles): array
    {
        $this->databaseName($targetDatabase);
        if (! preg_match('/^(cmc_restore_|cmc_mongo_test_restore_)[a-zA-Z0-9_]+$/D', $targetDatabase)
            || $targetDatabase === config('database.connections.mongodb.database') || file_exists($targetFiles)) {
            throw new RuntimeException('Đích restore phải là database cmc_restore_* và thư mục mới, không phải dữ liệu hiện hành.');
        }
        $client = DB::connection('mongodb')->getMongoClient();
        foreach ($client->listDatabases() as $database) {
            if ($database->getName() === $targetDatabase) {
                throw new RuntimeException('Database đích đã tồn tại; không ghi đè.');
            }
        }
        $manifest = $this->archive->unpackVerified($artifact, $targetFiles);
        $source = $manifest['source_database'] ?? '';
        $this->databaseName($source);
        $server = $client->selectDatabase('admin')->command(['buildInfo' => 1])->toArray()[0]['version'];
        if (explode('.', $manifest['mongo_version'] ?? '')[0] !== explode('.', $server)[0]) {
            throw new RuntimeException('Major version MongoDB khác bản backup; cần kiểm tra tương thích trước restore.');
        }
        $start = microtime(true);
        $this->tool('restore', ['--archive='.$targetFiles.'/database.archive.gz', '--gzip', '--nsInclude='.$source.'.*',
            '--nsFrom='.$source.'.*', '--nsTo='.$targetDatabase.'.*', '--stopOnError'], true);
        $actual = $this->describe($client->selectDatabase($targetDatabase));
        if ($this->canonical($actual) !== $this->canonical($manifest['collections'] ?? [])) {
            throw new RuntimeException('Dữ liệu/index/options sau restore không khớp manifest. Giữ target riêng để kiểm tra.');
        }
        $this->verifyCounters($client->selectDatabase($targetDatabase));
        $report = ['verified' => true, 'database' => $targetDatabase, 'files' => $targetFiles,
            'capture_at' => $manifest['capture_at'], 'restore_seconds' => round(microtime(true) - $start, 3),
            'verified_at' => now()->toIso8601String(), 'collections' => count($actual)];
        $this->repository->writeJson($targetFiles.'/restore-report.json', $report);
        $this->repository->writeJson($this->repository->root().'/restore-drill.json', ['verified_at' => now()->toIso8601String(), 'key_id' => $this->archive->keyId(), 'source_database' => $source, 'artifact_sha256' => hash_file('sha256', $artifact)]);

        return $report;
    }

    private function describe(Database $database): array
    {
        $result = [];
        foreach ($database->listCollections() as $info) {
            $name = $info->getName();
            if (in_array($name, config('backup.excluded'), true) || str_starts_with($name, 'system.')) {
                continue;
            }
            if ($info->getType() !== 'collection') {
                throw new RuntimeException('Phiên bản backup hiện tại chỉ hỗ trợ collection thường.');
            }
            $collection = $database->selectCollection($name);
            $hash = hash_init('sha256');
            $count = 0;
            foreach ($collection->find([], ['sort' => ['_id' => 1]]) as $document) {
                hash_update($hash, Document::fromPHP($document)->toCanonicalExtendedJSON()."\n");
                $count++;
            }
            $indexes = [];
            foreach ($collection->listIndexes() as $index) {
                $definition = $index->__debugInfo();
                unset($definition['ns']);
                $indexes[$index->getName()] = $definition;
            }
            $result[$name] = ['count' => $count, 'sha256' => hash_final($hash), 'indexes' => $indexes, 'options' => $info->getOptions()];
        }
        ksort($result);

        return json_decode($this->canonical($result), true, 512, JSON_THROW_ON_ERROR);
    }

    private function verifyCounters(Database $database): void
    {
        foreach (DocumentStore::schema() as $logical => $schema) {
            if (! ($schema['numeric'] ?? false)) {
                continue;
            }
            $collection = $database->selectCollection($schema['collection']);
            if (isset($schema['parent'])) {
                $rows = $collection->aggregate([
                    ['$unwind' => '$items'], ['$group' => ['_id' => null, 'maximum' => ['$max' => '$items.item_id']]],
                ])->toArray();
                $maximum = $rows[0]['maximum'] ?? null;
            } else {
                $row = $collection->findOne([], ['sort' => ['_id' => -1], 'projection' => ['_id' => 1]]);
                $maximum = $row['_id'] ?? null;
            }
            if ($maximum === null) {
                continue;
            }
            $counter = $database->selectCollection('_counters')->findOne(['_id' => $logical]);
            if (! is_numeric($maximum) || ! isset($counter['value']) || (int) $counter['value'] < (int) $maximum) {
                throw new RuntimeException('Counter '.$logical.' thiếu hoặc nhỏ hơn ID đã có. Giữ target riêng, chưa công nhận restore thành công.');
            }
        }
    }

    private function canonical(mixed $value): string
    {
        $array = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $sort = function (mixed &$item) use (&$sort): void {
            if (is_array($item)) {
                if (! array_is_list($item)) {
                    ksort($item);
                }
                foreach ($item as &$child) {
                    $sort($child);
                }
            }
        };
        $sort($array);

        return json_encode($array, JSON_THROW_ON_ERROR);
    }

    private function tool(string $type, array $arguments, bool $withUri = false): string
    {
        $config = null;
        try {
            if ($withUri) {
                $config = tempnam($this->repository->root(), '.mongo-');
                @chmod($config, 0600);
                file_put_contents($config, 'uri: '.json_encode(config('database.connections.mongodb.dsn'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n");
                array_unshift($arguments, '--config='.$config);
            }
            $process = new Process(array_merge([(string) config('backup.'.$type)], $arguments), base_path(), null, null, 300);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Công cụ MongoDB '.$type.' thất bại (exit '.$process->getExitCode().'). Kiểm đường dẫn, phiên bản, quyền và kết nối; không ghi URI vào log.');
            }

            return $process->getOutput();
        } finally {
            if ($config !== null) {
                @unlink($config);
            }
        }
    }

    private function databaseName(string $name): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]{1,63}$/D', $name) || in_array($name, ['admin', 'config', 'local'], true)) {
            throw new RuntimeException('Tên database backup/restore không hợp lệ.');
        }
    }

    private function inside(string|false $path, string|false $root): bool
    {
        if ($path === false || $root === false) {
            return false;
        }
        $path = strtolower(str_replace('\\', '/', $path));
        $root = strtolower(rtrim(str_replace('\\', '/', $root), '/'));

        return $path === $root || str_starts_with($path, $root.'/');
    }
}
