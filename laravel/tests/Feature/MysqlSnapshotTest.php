<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MysqlSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MysqlSnapshotTest extends TestCase
{
    use ResetsMongoDatabase;

    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            if (str_starts_with($directory, storage_path('framework/testing/snapshot-'))) {
                File::deleteDirectory($directory);
            }
        }
        parent::tearDown();
    }

    private function snapshot(): string
    {
        $directory = storage_path('framework/testing/snapshot-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        $this->directories[] = $directory;
        $rows = [
            'users' => [['id' => 101, 'name' => 'Snapshot customer', 'username' => 'snapshot@example.test', 'email' => 'snapshot@example.test', 'password' => Hash::make('OldPassword123'), 'role' => 'customer', 'status' => 'active']],
            'categories' => [['id' => 11, 'code' => 'CAT-SNAPSHOT', 'name' => 'Cát đá']],
            'products' => [['id' => 201, 'code' => 'P-SNAPSHOT', 'name' => 'Cát snapshot', 'category_id' => 11, 'unit' => 'm³', 'price' => '280000.00']],
            'orders' => [['id' => 301, 'code' => 'DH-SNAPSHOT', 'customer_id' => 101, 'delivery_address' => 'Test address', 'total' => '35000.00']],
            'order_items' => [['id' => 401, 'order_id' => 301, 'product_id' => 201, 'quantity' => '0.125', 'unit_price' => '280000.00', 'line_total' => '35000.00']],
            'sessions' => [['id' => 'old-session', 'payload' => 'must-not-be-replayed', 'last_activity' => 123]],
            'migrations' => [['id' => 1, 'migration' => 'legacy_sql_schema', 'batch' => 1]],
        ];
        $manifest = ['format' => 'cmc-mysql-snapshot-v1', 'source_commit' => 'test-fixture', 'tables' => []];
        foreach ($rows as $table => $records) {
            $path = $directory.'/'.$table.'.ndjson';
            file_put_contents($path, implode("\n", array_map(static fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $records))."\n");
            $manifest['tables'][$table] = ['rows' => count($records), 'sha256' => hash_file('sha256', $path)];
        }
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        return $directory;
    }

    public function test_dry_run_checks_snapshot_without_importing_records(): void
    {
        $result = app(MysqlSnapshot::class)->import($this->snapshot(), true);
        $this->assertSame(['dry_run' => true, 'tables' => 7, 'rows' => 7], $result);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::connection()->getCollection('_snapshot_imports')->countDocuments());
    }

    public function test_import_preserves_embedded_item_ids_hashes_and_exact_quantities_and_can_resume(): void
    {
        $directory = $this->snapshot();
        $sourceUser = json_decode(trim(file_get_contents($directory.'/users.ndjson')), true);
        $snapshots = app(MysqlSnapshot::class);
        $result = $snapshots->import($directory);
        $this->assertTrue($result['verified']);
        $this->assertSame(5, $result['business_rows']);
        $this->assertSame(2, $result['archived_runtime_rows']);
        $this->assertSame(0, DB::table('sessions')->count());
        $item = DB::table('order_items')->find(401);
        $this->assertSame(301, $item->order_id);
        $this->assertSame('0.125', $item->quantity);
        $raw = DB::connection()->getCollection('orders')->findOne(['_id' => 301]);
        $this->assertSame(401, $raw['items'][0]['item_id']);
        $this->assertSame($sourceUser['password'], User::find(101)->getRawOriginal('password'));
        $this->assertTrue(Hash::check('OldPassword123', User::find(101)->password));
        $this->assertSame(0, $snapshots->import($directory)['inserted']);
        $next = DB::table('products')->insertGetId(['code' => 'NEXT', 'name' => 'Next', 'unit' => 'kg']);
        $this->assertGreaterThan(201, $next);
    }

    public function test_import_after_migrate_keeps_native_migration_history_separate_from_archive(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $result = app(MysqlSnapshot::class)->import($this->snapshot());
        $this->assertTrue($result['verified']);
        $this->assertDatabaseCount('migrations', 1);
        $this->assertSame('2026_10_18_000001_create_mongodb_schema', DB::table('migrations')->first()->migration);
        $this->assertSame(2, $result['archived_runtime_rows']);
    }

    public function test_checksum_failure_is_rejected_before_any_import(): void
    {
        $directory = $this->snapshot();
        file_put_contents($directory.'/users.ndjson', "{}\n", FILE_APPEND);
        try {
            app(MysqlSnapshot::class)->import($directory);
            $this->fail('A changed snapshot must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
            $this->assertSame(0, DB::table('users')->count());
        }
    }

    public function test_existing_application_data_is_never_overwritten(): void
    {
        $directory = $this->snapshot();
        DB::table('products')->insert(['code' => 'EXISTING', 'name' => 'Existing', 'unit' => 'kg']);
        try {
            app(MysqlSnapshot::class)->import($directory);
            $this->fail('A nonempty target must be protected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not empty', $exception->getMessage());
            $this->assertSame('EXISTING', DB::table('products')->first()->code);
            $this->assertSame(0, DB::table('users')->count());
        }
    }

    public function test_verifier_reports_field_differences_without_changing_records(): void
    {
        $directory = $this->snapshot();
        $snapshots = app(MysqlSnapshot::class);
        $snapshots->import($directory);
        DB::table('products')->where('id', 201)->update(['price' => '1.00']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('products.price');
        $snapshots->verify($directory);
    }
}
