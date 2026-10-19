<?php

namespace Tests\Feature;

use App\Mongo\Schema;
use App\Services\CommerceWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\RuntimeException;
use Tests\Concerns\ResetsMongoDatabase;
use Tests\TestCase;

class MongoTransactionSafetyTest extends TestCase
{
    use ResetsMongoDatabase;

    public function test_independent_payment_transactions_conflict_without_double_collection(): void
    {
        $debt = DB::table('debts')->insertGetId(['code' => 'CONCURRENT', 'direction' => 'receivable', 'amount' => '100.00']);
        config(['database.connections.mongo_contender' => config('database.connections.mongodb')]);
        $contender = DB::connection('mongo_contender');
        $contender->beginTransaction();
        try {
            $contender->table('debts')->where('id', $debt)->lockForUpdate()->first();
            try {
                app(CommerceWorkflow::class)->recordPayment($debt, 1, '70.00', 'cash');
                $this->fail('A second session must not modify a debt held by the first transaction.');
            } catch (RuntimeException $exception) {
                $this->assertTrue($exception->hasErrorLabel('TransientTransactionError'));
            }
        } finally {
            $contender->rollBack();
            DB::purge('mongo_contender');
        }
        $this->assertSame('0.00', DB::table('debts')->find($debt)->paid);
        $this->assertDatabaseCount('payments', 0);
        app(CommerceWorkflow::class)->recordPayment($debt, 1, '70.00', 'cash');
        try {
            app(CommerceWorkflow::class)->recordPayment($debt, 1, '40.00', 'cash');
            $this->fail('Retrying after the conflict must still enforce the outstanding balance.');
        } catch (ValidationException) {
            $this->assertSame('70.00', DB::table('debts')->find($debt)->paid);
            $this->assertDatabaseCount('payments', 1);
        }
    }

    public function test_transient_conflict_retries_the_whole_payment_and_rechecks_new_balance(): void
    {
        $debt = DB::table('debts')->insertGetId(['code' => 'RETRY', 'direction' => 'receivable', 'amount' => '100.00']);
        config(['database.connections.mongo_contender' => config('database.connections.mongodb')]);
        $contender = DB::connection('mongo_contender');
        $contender->beginTransaction();
        $contender->table('debts')->where('id', $debt)->lockForUpdate()->first();
        $contender->table('debts')->where('id', $debt)->update(['paid' => '70.00']);
        $attempts = 0;
        DB::connection()->beforeStartingTransaction(function () use ($contender, &$attempts): void {
            if (++$attempts === 2) {
                $contender->commit();
            }
        });
        try {
            try {
                app(CommerceWorkflow::class)->recordPayment($debt, 1, '40.00', 'cash');
                $this->fail('A retried payment must validate the newly committed outstanding balance.');
            } catch (ValidationException) {
                $this->assertSame(2, $attempts);
                $this->assertSame('70.00', DB::table('debts')->find($debt)->paid);
                $this->assertDatabaseCount('payments', 0);
            }
        } finally {
            if ($contender->transactionLevel() > 0) {
                $contender->rollBack();
            }
            DB::purge('mongo_contender');
            DB::purge('mongodb');
        }
    }

    public function test_payment_insert_failure_rolls_back_guard_and_counter_before_retry(): void
    {
        $debt = DB::table('debts')->insertGetId(['code' => 'FAULT', 'direction' => 'receivable', 'amount' => '100.00']);
        DB::connection()->getDatabase()->command(['collMod' => 'payments', 'validator' => ['$jsonSchema' => ['required' => ['injected_failure']]]])->toArray();
        try {
            try {
                app(CommerceWorkflow::class)->recordPayment($debt, 1, '20.00', 'cash');
                $this->fail('The injected server validation failure must abort the workflow.');
            } catch (BulkWriteException $exception) {
                $this->assertSame(121, $exception->getCode());
            }
        } finally {
            Schema::create(DB::connection());
        }
        $this->assertSame('0.00', DB::table('debts')->find($debt)->paid);
        $this->assertDatabaseCount('payments', 0);
        $this->assertNull(DB::connection()->getCollection('debts')->findOne(['_id' => $debt])->_lock_version ?? null);
        $payment = app(CommerceWorkflow::class)->recordPayment($debt, 1, '20.00', 'cash');
        $this->assertSame(1, $payment);
        $this->assertSame('20.00', DB::table('debts')->find($debt)->paid);
    }

    public function test_independent_sessions_cannot_write_the_same_stock_reservation_guard(): void
    {
        $inventory = DB::table('inventory')->insertGetId(['warehouse_id' => 1, 'product_id' => 1, 'quantity' => '5.000']);
        config(['database.connections.mongo_contender' => config('database.connections.mongodb')]);
        $contender = DB::connection('mongo_contender');
        DB::beginTransaction();
        try {
            DB::table('inventory')->where('id', $inventory)->lockForUpdate()->first();
            try {
                $contender->transaction(fn () => $contender->table('inventory')->where('id', $inventory)->lockForUpdate()->first());
                $this->fail('Concurrent reservations must conflict on the shared inventory document.');
            } catch (RuntimeException $exception) {
                $this->assertTrue($exception->hasErrorLabel('TransientTransactionError'));
            }
            DB::table('inventory')->where('id', $inventory)->update(['quantity' => '4.000']);
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('mongo_contender');
        }
        $this->assertSame('4.000', DB::table('inventory')->find($inventory)->quantity);
    }
}
