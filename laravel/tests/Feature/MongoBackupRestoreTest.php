<?php

namespace Tests\Feature;

use App\Mongo\DocumentStore;
use App\Services\Backup\BackupArchive;
use App\Services\Backup\MongoBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class MongoBackupRestoreTest extends TestCase
{
    public function test_native_dump_encrypted_restore_preserves_bson_indexes_files_and_deletions(): void
    {
        $suffix = str_replace('-', '', (string) Str::uuid());
        $source = 'cmc_mongo_test_backup_'.$suffix;
        $target = 'cmc_mongo_test_restore_'.$suffix;
        $root = base_path('../.runtime/backup-tests/'.$suffix);
        $original = config('database.connections.mongodb.database');
        config(['database.connections.mongodb.database' => $source, 'backup.root' => $root.'/state',
            'backup.sources' => ['private' => $root.'/private', 'public' => $root.'/public'],
            'backup.key' => base64_encode(random_bytes(32)),
            'backup.dump' => 'C:/Program Files/MongoDB/Tools/100/bin/mongodump.exe',
            'backup.restore' => 'C:/Program Files/MongoDB/Tools/100/bin/mongorestore.exe']);
        DB::purge('mongodb');
        $client = DB::connection('mongodb')->getMongoClient();
        try {
            $database = $client->selectDatabase($source);
            $database->createCollection('products', ['validator' => ['$jsonSchema' => ['bsonType' => 'object', 'properties' => ['price' => ['bsonType' => 'decimal']]]], 'collation' => ['locale' => 'vi', 'strength' => 2]]);
            $database->selectCollection('products')->insertMany([
                ['_id' => 1, 'price' => new Decimal128('123.45'), 'at' => new UTCDateTime(1000), 'items' => [['item_id' => 5]]],
                ['_id' => 2, 'deleted' => true],
            ]);
            $database->selectCollection('products')->createIndex(['price' => 1], ['name' => 'price_index']);
            $database->selectCollection('products')->deleteOne(['_id' => 2]);
            $database->selectCollection('_counters')->insertOne(['_id' => 'products', 'value' => 10]);
            $database->selectCollection('sessions')->insertOne(['_id' => 'do-not-restore']);
            File::ensureDirectoryExists($root.'/private/contracts');
            file_put_contents($root.'/private/contracts/one.pdf', 'private contract');
            $mongo = app(MongoBackup::class);
            $manifest = $mongo->capture($root.'/capture');
            $archive = app(BackupArchive::class);
            $archive->pack($root.'/capture', $root.'/plain.zip');
            $archive->encrypt($root.'/plain.zip', $root.'/backup.cmcbackup');
            $report = $mongo->restore($root.'/backup.cmcbackup', $target, $root.'/restored');
            $this->assertTrue($report['verified']);
            $restored = $client->selectDatabase($target)->selectCollection('products');
            $this->assertSame(1, $restored->countDocuments());
            $this->assertInstanceOf(Decimal128::class, $restored->findOne(['_id' => 1])['price']);
            $this->assertInstanceOf(UTCDateTime::class, $restored->findOne(['_id' => 1])['at']);
            $this->assertSame('private contract', file_get_contents($root.'/restored/files/private/contracts/one.pdf'));
            $this->assertNotContains('sessions', iterator_to_array($client->selectDatabase($target)->listCollectionNames()));
            $this->assertNotEmpty($manifest['collections']);
            config(['database.connections.mongodb.database' => $target]);
            DB::purge('mongodb');
            $store = new DocumentStore(DB::connection('mongodb'));
            $this->assertSame(11, $store->insert('products', ['code' => 'AFTER-RESTORE', 'name' => 'New product', 'unit' => 'm3', 'price' => '1.00', 'active' => true]));
            config(['database.connections.mongodb.database' => $source]);
            DB::purge('mongodb');
            $this->expectException(\RuntimeException::class);
            $mongo->restore($root.'/backup.cmcbackup', $target, $root.'/another');
        } finally {
            $client->dropDatabase($source);
            $client->dropDatabase($target);
            config(['database.connections.mongodb.database' => $original]);
            DB::purge('mongodb');
            File::deleteDirectory($root);
        }
    }

    public function test_restore_refuses_a_counter_behind_existing_business_ids(): void
    {
        $suffix = str_replace('-', '', (string) Str::uuid());
        $source = 'cmc_mongo_test_backup_'.$suffix;
        $target = 'cmc_mongo_test_restore_'.$suffix;
        $root = base_path('../.runtime/backup-tests/'.$suffix);
        $original = config('database.connections.mongodb.database');
        config(['database.connections.mongodb.database' => $source, 'backup.root' => $root.'/state', 'backup.sources' => [],
            'backup.key' => base64_encode(random_bytes(32)), 'backup.dump' => 'C:/Program Files/MongoDB/Tools/100/bin/mongodump.exe', 'backup.restore' => 'C:/Program Files/MongoDB/Tools/100/bin/mongorestore.exe']);
        DB::purge('mongodb');
        $client = DB::connection('mongodb')->getMongoClient();
        try {
            $client->selectDatabase($source)->selectCollection('products')->insertOne(['_id' => 10]);
            $client->selectDatabase($source)->selectCollection('_counters')->insertOne(['_id' => 'products', 'value' => 1]);
            $mongo = app(MongoBackup::class);
            $mongo->capture($root.'/capture');
            $archive = app(BackupArchive::class);
            $archive->pack($root.'/capture', $root.'/plain.zip');
            $archive->encrypt($root.'/plain.zip', $root.'/backup.cmcbackup');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Counter');
            $mongo->restore($root.'/backup.cmcbackup', $target, $root.'/restored');
        } finally {
            $client->dropDatabase($source);
            $client->dropDatabase($target);
            config(['database.connections.mongodb.database' => $original]);
            DB::purge('mongodb');
            File::deleteDirectory($root);
        }
    }
}
