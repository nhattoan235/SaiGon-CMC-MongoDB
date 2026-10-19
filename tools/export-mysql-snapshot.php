<?php
declare(strict_types=1);

$source = realpath($argv[1] ?? '');
$destination = $argv[2] ?? '';
if (! $source || ! is_file($source.'/vendor/autoload.php') || $destination === '') {
    fwrite(STDERR, "Usage: php export-mysql-snapshot.php <source-laravel-directory> <new-private-snapshot-directory>\n");
    exit(2);
}
if (file_exists($destination)) {
    fwrite(STDERR, "Snapshot destination must not already exist.\n");
    exit(2);
}
require $source.'/vendor/autoload.php';
$app = require $source.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (isset($argv[3])) {
    config(['database.connections.mysql.port' => (int) $argv[3]]);
    Illuminate\Support\Facades\DB::purge('mysql');
}
if (Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "The source must be the existing MySQL application.\n");
    exit(2);
}
$connection = Illuminate\Support\Facades\DB::connection();
$pdo = $connection->getPdo();
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
mkdir($destination, 0700, true);
try {
    $tableNames = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    $manifest = [
        'format' => 'cmc-mysql-snapshot-v1',
        'created_at' => gmdate(DATE_ATOM),
        'app_timezone' => config('app.timezone', 'UTC'),
        'sql_session_timezone' => $pdo->query('SELECT @@session.time_zone')->fetchColumn(),
        'source_commit' => 'f9860db0e594aefb8b2bb86ae81bb2306086ac17',
        'tables' => [],
    ];
    foreach ($tableNames as $table) {
        if (! preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected source table name.');
        }
        $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $primary = array_column(array_filter($columns, static fn ($column) => $column['Key'] === 'PRI'), 'Field');
        $order = $primary ? ' ORDER BY '.implode(',', array_map(static fn ($field) => '`'.$field.'`', $primary)) : '';
        $statement = $pdo->query('SELECT * FROM `'.$table.'`'.$order);
        $path = $destination.'/'.$table.'.ndjson';
        $file = fopen($path, 'xb');
        $count = 0;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            fwrite($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
            $count++;
        }
        fclose($file);
        $manifest['tables'][$table] = [
            'rows' => $count, 'columns' => $columns, 'primary_key' => array_values($primary),
            'sha256' => hash_file('sha256', $path),
        ];
    }
    $pdo->commit();
    file_put_contents($destination.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo json_encode(['snapshot_tables' => count($manifest['tables']), 'total_rows' => array_sum(array_column($manifest['tables'], 'rows')), 'path' => $destination], JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Snapshot failed; source transaction rolled back. Error type: '.get_class($exception)."\n");
    exit(1);
}
