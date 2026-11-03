<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommerceWorkflow;
use App\Services\FulfillmentWorkflow;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MysqlTransactionSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if (! (($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'cmms_qa_validation')
            || ($connection->getDriverName() === 'mongodb' && str_starts_with($connection->getDatabaseName(), 'cmms_mongo_test')))) {
            $this->markTestSkipped('Chỉ chạy trên database QA MySQL/MongoDB riêng.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    private function fixture(): array
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $sales = User::factory()->create(['role' => 'sales']);
        $accountant = User::factory()->create(['role' => 'accounting']);
        $staff = User::factory()->create(['role' => 'warehouse']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'QA-LOCK', 'name' => 'Kho QA', 'active' => true]);
        $product = DB::table('products')->insertGetId(['code' => 'QA-LAST', 'name' => 'Gạch QA', 'unit' => 'viên', 'price' => '100.00', 'active' => true, 'direct_purchase_allowed' => true]);
        DB::table('inventory')->insert(['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => '1.000']);
        $order = app(CommerceWorkflow::class)->placePickupOrder($customer->id, [$product => 1], $warehouse);
        $debt = DB::table('debts')->where('order_id', $order)->value('id');

        return compact('customer', 'sales', 'accountant', 'staff', 'warehouse', 'product', 'order', 'debt');
    }

    public function test_two_concurrent_confirmations_cannot_reserve_the_last_item_twice(): void
    {
        $f = $this->fixture();
        $second = User::factory()->create(['role' => 'customer']);
        $other = app(CommerceWorkflow::class)->placePickupOrder($second->id, [$f['product'] => 1], $f['warehouse']);
        $processes = [];
        $signals = [];
        DB::beginTransaction();
        DB::table('warehouses')->where('id', $f['warehouse'])->lockForUpdate()->first();
        try {
            foreach ([$f['order'], $other] as $order) {
                $signal = sys_get_temp_dir().'/cmc-race-'.Str::uuid();
                $signals[] = $signal;
                $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                    .(DB::connection()->getDriverName() === 'mongodb' ? 'file_put_contents('.var_export($signal, true).',"ready"); ' : '')
                    .'Illuminate\\Support\\Facades\\DB::listen(function($q){if(str_contains($q->sql,"from `orders`") && str_contains($q->sql,"for update")){file_put_contents('.var_export($signal, true).',"ready");}}); '
                    .'try {app(App\\Services\\CommerceWorkflow::class)->reviewOrder('.$order.','.$f['sales']->id.',true); echo "accepted";} catch (Illuminate\\Validation\\ValidationException $e) {echo "rejected";}';
                $process = new Process([PHP_BINARY, '-r', $code], base_path(), timeout: 90);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 60;
            while (collect($signals)->contains(fn ($path) => ! is_file($path)) && microtime(true) < $deadline) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Worker dừng trước điểm đồng bộ: '.$process->getErrorOutput());
                    }
                }
                usleep(20000);
            }
            foreach ($signals as $signal) {
                $this->assertFileExists($signal, 'Cả hai worker phải giữ khóa đơn hàng trước khi nhả khóa kho.');
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = trim($process->getOutput());
            }
            sort($results);
            $this->assertSame(['accepted', 'rejected'], $results);
            $reserved = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.status', 'awaiting_payment')->sum('quantity');
            $this->assertEquals(1, $reserved);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ($signals as $signal) {
                if (is_file($signal)) {
                    unlink($signal);
                }
            }
        }
    }

    public function test_payment_failure_rolls_back_payment_allocations_and_order_balances(): void
    {
        $f = $this->fixture();
        app(CommerceWorkflow::class)->reviewOrder($f['order'], $f['sales']->id, true);
        $before = (array) DB::table('orders')->find($f['order']);
        $this->injectFailureAfterInsert('payments');
        try {
            app(CommerceWorkflow::class)->recordPayment($f['debt'], $f['accountant']->id, '100.00', 'cash');
            $this->fail('Phải phát sinh lỗi đã chèn.');
        } catch (RuntimeException $exception) {
            $this->assertSame('QA injected failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame($before, (array) DB::table('orders')->find($f['order']));
    }

    public function test_stock_issue_failure_restores_inventory_and_document_history(): void
    {
        $f = $this->fixture();
        $commerce = app(CommerceWorkflow::class);
        $commerce->reviewOrder($f['order'], $f['sales']->id, true);
        $commerce->recordPayment($f['debt'], $f['accountant']->id, '100.00', 'cash');
        $commerce->issueInvoice($f['order'], $f['accountant']->id);
        $this->injectFailureAfterInsert('stock_movements');
        try {
            app(FulfillmentWorkflow::class)->issueOrder($f['order'], $f['warehouse'], $f['staff']->id);
            $this->fail('Phải phát sinh lỗi đã chèn.');
        } catch (RuntimeException $exception) {
            $this->assertSame('QA injected failure', $exception->getMessage());
        }
        $this->assertEquals('1.000', DB::table('inventory')->value('quantity'));
        $this->assertDatabaseCount('stock_documents', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_document_items', 0);
        $this->assertSame('ready_to_issue', DB::table('orders')->find($f['order'])->status);
    }

    private function injectFailureAfterInsert(string $table): void
    {
        $armed = true;
        DB::listen(function (QueryExecuted $query) use ($table, &$armed): void {
            if ($armed && (str_starts_with(strtolower($query->sql), 'insert into `'.$table.'`') || $query->sql === 'mongodb.insert '.$table)) {
                $armed = false;
                throw new RuntimeException('QA injected failure');
            }
        });
    }
}
