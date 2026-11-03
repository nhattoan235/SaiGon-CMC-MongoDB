<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MongoExportMysql extends Command
{
    protected $signature = 'cmms:mongo-export-mysql {database : Database MySQL có schema trống, bắt đầu bằng cmms_rollback_}';

    protected $description = 'Xuất dữ liệu MongoDB hiện tại về database MySQL mới và đối soát để quay lui';

    public function handle(): int
    {
        $target = $this->argument('database');
        if (! preg_match('/^cmms_rollback_[a-z0-9_]+$/', $target)) {
            throw new RuntimeException('Chỉ được xuất về database mới có tiền tố cmms_rollback_.');
        }
        config(['database.connections.rollback_mysql' => array_replace(config('database.connections.mysql'), ['database' => $target])]);
        DB::purge('rollback_mysql');
        $mysql = DB::connection('rollback_mysql');
        $mongo = DB::connection('mongodb');
        foreach (array_keys($mongo->definitions) as $table) {
            if ($mysql->table($table)->exists()) {
                throw new RuntimeException('Database quay lui phải có schema trống; không ghi đè '.$table);
            }
        }
        $counts = [];
        $mysql->getSchemaBuilder()->disableForeignKeyConstraints();
        try {
            $mongo->transaction(function () use ($mongo, $mysql, &$counts): void {
                $mysql->transaction(function () use ($mongo, $mysql, &$counts): void {
                    foreach ($mongo->definitions as $table => $definition) {
                        $rows = $mongo->table($table)->get()->map(fn ($row) => (array) $row)->all();
                        foreach (array_chunk($rows, 100) as $batch) {
                            $mysql->table($table)->insert($batch);
                        }
                        $actual = $mysql->table($table)->get()->map(fn ($row) => (array) $row)->all();
                        if (count($rows) !== count($actual) || $this->digest($rows, $definition) !== $this->digest($actual, $definition)) {
                            throw new RuntimeException('Dữ liệu quay lui không khớp: '.$table);
                        }
                        $counts[$table] = count($rows);
                    }
                    foreach ($mongo->definitions as $table => $definition) {
                        foreach ($definition['foreign_keys'] as $foreign) {
                            $query = $mysql->table($table.' as child');
                            foreach ($foreign['columns'] as $column) {
                                $query->whereNotNull('child.'.$column);
                            }
                            $query->whereNotExists(function ($parent) use ($foreign): void {
                                $parent->selectRaw('1')->from($foreign['foreign_table'].' as parent');
                                foreach ($foreign['columns'] as $i => $column) {
                                    $parent->whereColumn('child.'.$column, 'parent.'.$foreign['foreign_columns'][$i]);
                                }
                            });
                            if ($query->exists()) {
                                throw new RuntimeException('Tham chiếu quay lui không hợp lệ: '.$table);
                            }
                        }
                    }
                });
            });
        } finally {
            $mysql->getSchemaBuilder()->enableForeignKeyConstraints();
        }
        $this->line(json_encode(['database' => $target, 'verified_tables' => count($counts), 'rows' => $counts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function digest(array $rows, array $definition): string
    {
        $hashes = [];
        foreach ($rows as $row) {
            foreach ($definition['columns'] as $column) {
                $name = $column['name'];
                if ($row[$name] !== null && $column['type_name'] === 'json') {
                    $row[$name] = json_decode($row[$name], true, 512, JSON_THROW_ON_ERROR);
                }
            }
            $hashes[] = hash('sha256', json_encode($this->canonical($row), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        sort($hashes);

        return hash('sha256', implode('', $hashes));
    }

    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            $value = array_map($this->canonical(...), $value);
        }

        return $value;
    }
}
