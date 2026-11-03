<?php

namespace Tests\Feature;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MongoFrameworkStoresTest extends TestCase
{
    use ResetsMongoDatabase;

    public function test_native_session_handler_round_trips_binary_payload_and_expiry(): void
    {
        $session = app('session')->driver('mongodb');
        $session->setId(Str::random(40));
        $session->put('proof', 'mongo-session');
        $session->save();
        $stored = DB::connection()->getCollection('sessions')->findOne(['_id' => $session->getId()]);
        $this->assertInstanceOf(Binary::class, $stored->payload);
        $this->assertInstanceOf(UTCDateTime::class, $stored->expires_at);
        $this->assertStringContainsString('mongo-session', $session->getHandler()->read($session->getId()));
        $session->getHandler()->destroy($session->getId());
        $this->assertNull(DB::connection()->getCollection('sessions')->findOne(['_id' => $session->getId()]));
    }

    public function test_native_cache_and_locks_use_mongo_documents(): void
    {
        $cache = Cache::store('mongodb');
        $this->assertTrue($cache->put('proof', ['value' => 'mongo-cache'], 60));
        $this->assertSame(['value' => 'mongo-cache'], $cache->get('proof'));
        $first = $cache->lock('proof-lock', 60);
        $second = $cache->lock('proof-lock', 60);
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $this->assertTrue($first->release());
        $this->assertTrue($second->get());
        $this->assertTrue($second->release());
        $row = DB::connection()->getCollection('cache')->findOne([]);
        $this->assertInstanceOf(UTCDateTime::class, $row->expires_at);
        $this->assertTrue($cache->forget('proof'));
        $this->assertNull($cache->get('proof'));
    }

    public function test_native_queue_reserves_executes_and_deletes_a_job(): void
    {
        $queue = Queue::connection('mongodb');
        $id = $queue->push(new MongoFrameworkStoreJob);
        $this->assertIsInt($id);
        $job = $queue->pop();
        $this->assertNotNull($job);
        $this->assertSame(1, $job->attempts());
        $job->fire();
        $job->delete();
        $this->assertSame('handled', Cache::store('mongodb')->get('native-queue-proof'));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_native_batch_and_failed_job_repositories_preserve_recovery_records(): void
    {
        $batch = Bus::batch([new MongoFrameworkStoreJob])->onConnection('mongodb')->dispatch();
        $saved = Bus::findBatch($batch->id);
        $this->assertNotNull($saved);
        $this->assertSame(1, $saved->totalJobs);
        $this->assertDatabaseCount('jobs', 1);
        $failer = app('queue.failer');
        $uuid = (string) Str::uuid();
        $id = $failer->log('mongodb', 'default', json_encode(['uuid' => $uuid]), new \RuntimeException('Controlled failure'));
        $this->assertSame($uuid, $id);
        $this->assertSame('mongodb', $failer->find($id)->connection);
        $this->assertTrue($failer->forget($id));
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}

class MongoFrameworkStoreJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function handle(): void
    {
        Cache::store('mongodb')->put('native-queue-proof', 'handled', 60);
    }
}
