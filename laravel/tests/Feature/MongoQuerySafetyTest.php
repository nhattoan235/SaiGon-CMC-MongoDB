<?php

namespace Tests\Feature;

use App\Jobs\IndexKnowledgeDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class MongoQuerySafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mongodb') {
            $this->markTestSkipped('Kiểm thử dành cho MongoDB.');
        }
    }

    public function test_explicit_ids_advance_the_sequence_and_nullable_unique_fields_allow_multiple_nulls(): void
    {
        $role = DB::table('roles')->where('code', 'customer')->value('id');
        DB::table('users')->insert(['id' => 100, 'name' => 'A', 'username' => 'qa_a', 'role' => 'customer', 'role_id' => $role, 'password' => 'hash']);
        $next = DB::table('users')->insertGetId(['name' => 'B', 'username' => 'qa_b', 'role' => 'customer', 'role_id' => $role, 'password' => 'hash']);
        $this->assertSame(101, $next);
        $this->assertSame(2, DB::table('users')->whereNull('email')->count());
    }

    public function test_decimal_math_and_accent_insensitive_search_preserve_catalog_behavior(): void
    {
        $id = DB::table('products')->insertGetId(['code' => 'QA-MONEY', 'name' => 'Xi măng', 'unit' => 'bao', 'price' => '0.10']);
        DB::table('products')->where('id', $id)->increment('price', '0.20');
        $this->assertSame('0.30', DB::table('products')->where('id', $id)->value('price'));
        $this->assertSame($id, DB::table('products')->where('name', 'like', '%xi mang%')->value('id'));
    }

    public function test_broken_references_abort_the_entire_write(): void
    {
        try {
            DB::table('products')->insert(['code' => 'QA-ORPHAN', 'name' => 'QA', 'unit' => 'bao', 'category_id' => 987654]);
            $this->fail('Không được lưu tham chiếu mồ côi.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('products')->count());
        }
    }

    public function test_after_commit_callbacks_only_run_after_durable_commit(): void
    {
        $seen = [];
        DB::transaction(function () use (&$seen): void {
            DB::table('categories')->insert(['code' => 'QA-COMMIT', 'name' => 'QA']);
            DB::afterCommit(function () use (&$seen): void {
                $seen = [DB::transactionLevel(), DB::table('categories')->count()];
            });
            $this->assertSame([], $seen);
        });
        $this->assertSame([0, 1], $seen);
        try {
            DB::transaction(function () use (&$seen): void {
                DB::table('categories')->insert(['code' => 'QA-ABORT', 'name' => 'QA']);
                DB::afterCommit(function () use (&$seen): void {
                    $seen = ['invalid'];
                });
                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
            $this->assertSame([0, 1], $seen);
            $this->assertSame(1, DB::table('categories')->count());
        }
    }

    public function test_caught_nested_transaction_errors_cannot_commit_partial_data(): void
    {
        try {
            DB::transaction(function (): void {
                DB::table('categories')->insert(['code' => 'QA-NESTED', 'name' => 'QA']);
                try {
                    DB::transaction(function (): void {
                        throw new RuntimeException('nested');
                    });
                } catch (RuntimeException) {
                    // The outer transaction must still abort.
                }
            });
            $this->fail('Không được commit một transaction đã lỗi.');
        } catch (RuntimeException) {
            $this->assertSame(0, DB::table('categories')->count());
        }
    }

    public function test_real_queue_jobs_can_be_popped_and_acknowledged_without_pdo(): void
    {
        $queue = Queue::connection('rag');
        $queue->push(new IndexKnowledgeDocument((string) Str::uuid(), (string) Str::uuid()), '', 'rag');
        $this->assertSame(1, DB::table('jobs')->count());
        $job = $queue->pop('rag');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_queued_after_commit_jobs_wait_for_the_mongodb_commit(): void
    {
        DB::transaction(function (): void {
            IndexKnowledgeDocument::dispatch((string) Str::uuid(), (string) Str::uuid())->afterCommit();
            $this->assertSame(0, DB::table('jobs')->count());
        });
        $this->assertSame(1, DB::table('jobs')->count());
        try {
            DB::transaction(function (): void {
                IndexKnowledgeDocument::dispatch((string) Str::uuid(), (string) Str::uuid())->afterCommit();
                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
            $this->assertSame(1, DB::table('jobs')->count());
        }
    }
}
