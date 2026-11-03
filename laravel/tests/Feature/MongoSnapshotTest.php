<?php

namespace Tests\Feature;

use App\Services\MongoSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MongoDB\BSON\Decimal128;
use MongoDB\Client;
use RuntimeException;
use Tests\TestCase;

class MongoSnapshotTest extends TestCase
{
    public function test_import_refuses_a_production_database_name_before_connecting(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cmms_rehearsal_');
        app(MongoSnapshot::class)->import('cmms_production');
    }

    public function test_snapshot_preserves_every_row_and_refuses_overwriting_existing_data(): void
    {
        if (! extension_loaded('mongodb') || ! filter_var(env('MONGO_MIGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Cần MongoDB replica set và MONGO_MIGRATION_TESTS=1.');
        }
        $target = 'cmms_rehearsal_test_'.str_replace('-', '', (string) Str::uuid());
        $client = new Client(config('database.connections.mongodb.dsn'));
        $mongo = $client->selectDatabase($target);
        try {
            $report = app(MongoSnapshot::class)->import($target);
            $source = DB::connection('mysql');
            $this->assertCount(count($source->getSchemaBuilder()->getTableListing(schema: $source->getDatabaseName(), schemaQualified: false)), $report['tables']);
            $this->assertGreaterThan(0, $report['foreign_keys_checked']);
            $sourceProduct = DB::connection('mysql')->table('products')->first();
            $this->assertNotNull($sourceProduct, 'Database kiểm thử cần có dữ liệu sản phẩm.');
            $product = $mongo->selectCollection('products')->findOne(['id' => $sourceProduct->id]);
            $this->assertInstanceOf(Decimal128::class, $product['price']);
            $this->assertSame((string) $sourceProduct->price, (string) $product['price']);
            $this->assertFalse($mongo->selectCollection('_migration_manifest')->findOne(['_id' => 'mysql_snapshot'])['application_ready']);
            $before = $mongo->selectCollection('products')->countDocuments();
            try {
                app(MongoSnapshot::class)->import($target);
                $this->fail('Không được ghi đè snapshot đã có.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('không ghi đè', $exception->getMessage());
            }
            $this->assertSame($before, $mongo->selectCollection('products')->countDocuments());
            $this->assertSame(count($report['tables']), app(MongoSnapshot::class)->verify($target)['verified_tables']);
            $mongo->selectCollection('products')->updateOne(['id' => $sourceProduct->id], ['$set' => ['price' => new Decimal128('999999.99')]]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Snapshot không khớp: products');
            app(MongoSnapshot::class)->verify($target);
        } finally {
            $client->dropDatabase($target);
        }
    }

    public function test_mongodb_aborts_both_collections_when_a_transaction_fails(): void
    {
        if (! extension_loaded('mongodb') || ! filter_var(env('MONGO_MIGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Cần MongoDB replica set và MONGO_MIGRATION_TESTS=1.');
        }
        $target = 'cmms_rehearsal_transaction_'.str_replace('-', '', (string) Str::uuid());
        $client = new Client(config('database.connections.mongodb.dsn'));
        $mongo = $client->selectDatabase($target);
        $session = $client->startSession();
        try {
            $mongo->createCollection('balances');
            $mongo->createCollection('payments');
            $mongo->selectCollection('balances')->insertOne(['_id' => 1, 'amount' => new Decimal128('100.00')]);
            $session->startTransaction();
            $mongo->selectCollection('balances')->updateOne(['_id' => 1], ['$set' => ['amount' => new Decimal128('0.00')]], ['session' => $session]);
            $mongo->selectCollection('payments')->insertOne(['_id' => 1, 'amount' => new Decimal128('100.00')], ['session' => $session]);
            $session->abortTransaction();
            $this->assertSame('100.00', (string) $mongo->selectCollection('balances')->findOne(['_id' => 1])['amount']);
            $this->assertSame(0, $mongo->selectCollection('payments')->countDocuments());
        } finally {
            $session->endSession();
            $client->dropDatabase($target);
        }
    }
}
