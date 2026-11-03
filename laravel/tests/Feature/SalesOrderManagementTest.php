<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommerceWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class SalesOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_order_skips_contract_and_requires_full_payment_before_release(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $accountant = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'K1', 'name' => 'Kho chính']);
        $product = DB::table('products')->insertGetId(['code' => 'XM-1', 'name' => 'Xi măng', 'unit' => 'bao', 'price' => 92000, 'direct_purchase_allowed' => true]);
        DB::table('inventory')->insert(['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 3]);
        $orderId = app(CommerceWorkflow::class)->placePickupOrder($customer->id, [$product => 2], $warehouse);
        $debtId = DB::table('debts')->where('order_id', $orderId)->value('id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'status' => 'awaiting_confirmation', 'requires_contract' => false,
            'contract_id' => null,
        ]);
        $this->actingAs($accountant)->get('/staff/work/finance')->assertOk()->assertDontSee('Ghi nhận</button>', false);
        $this->actingAs($accountant)->post('/staff/debts/'.$debtId.'/payments', ['amount' => 184000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('workflow');
        $this->actingAs($customer)->get('/my/profile')->assertOk()
            ->assertViewHas('debts', fn ($debts) => ! $debts->contains('id', $debtId));
        $this->actingAs($sales)->get('/staff/work/sales_orders')->assertOk()
            ->assertSee('Xác nhận đơn')->assertSee('Xi măng');
        $this->actingAs($sales)->post('/staff/orders/'.$orderId.'/review', ['decision' => 'confirm'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'awaiting_payment', 'reviewed_by' => $sales->id]);
        $this->assertDatabaseCount('contracts', 0);
        $this->actingAs($sales)->get('/staff/work/sales_orders')->assertOk()
            ->assertSee('Chờ thanh toán')->assertDontSee('Lập hợp đồng từ đơn');
        $this->actingAs($customer)->get('/my/profile')->assertOk()
            ->assertViewHas('debts', fn ($debts) => $debts->contains('id', $debtId));
        $this->actingAs($accountant)->get('/staff/work/finance')->assertOk()->assertSee('Ghi nhận</button>', false);
        $this->actingAs($accountant)->post('/staff/debts/'.$debtId.'/payments', ['amount' => 92000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'awaiting_payment', 'paid' => 92000]);
        $this->actingAs($accountant)->post('/staff/orders/'.$orderId.'/invoice')->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('invoices', 0);
        $this->actingAs($accountant)->post('/staff/debts/'.$debtId.'/payments', ['amount' => 92000, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'ready_to_issue']);
        $this->actingAs($accountant)->post('/staff/orders/'.$orderId.'/invoice')->assertRedirect();
        $this->assertNotNull(DB::table('orders')->find($orderId)->released_by);
        $this->actingAs($warehouseStaff = User::factory()->create(['role' => 'warehouse', 'status' => 'active']))
            ->post('/staff/orders/'.$orderId.'/issue', ['warehouse_id' => $warehouse])->assertRedirect();
        $this->actingAs($warehouseStaff)->post('/staff/orders/'.$orderId.'/pickup', ['recipient' => 'Nguyễn Văn A'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'delivered', 'pickup_recipient' => 'Nguyễn Văn A']);
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_sales_order_lookup_paginates_beyond_the_old_hundred_row_limit(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (range(1, 105) as $number) {
            DB::table('orders')->insert([
                'code' => sprintf('DH-PAGE-%03d', $number), 'customer_id' => $customer->id,
                'contract_id' => null, 'status' => 'awaiting_confirmation', 'total' => '100.00', 'paid' => '0.00',
                'delivery_address' => 'Nhận tại kho', 'fulfillment_method' => 'pickup', 'requires_contract' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $url = '/staff/work/sales_orders?q=DH-PAGE&status=awaiting_confirmation';
        $this->actingAs($sales)->get($url)->assertOk()->assertSee('105 đơn')
            ->assertSee('page=4', false)->assertSee('q=DH-PAGE', false)->assertSee('status=awaiting_confirmation', false);
        $this->actingAs($sales)->get($url.'&page=4')->assertOk()
            ->assertSee('DH-PAGE-001')->assertDontSee('DH-PAGE-105')
            ->assertSee('value="awaiting_confirmation" selected', false);
    }

    public function test_sales_order_search_handles_blank_and_no_match_queries_without_writes(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        DB::table('orders')->insert([
            'code' => 'DH-SEARCH-001', 'customer_id' => $customer->id, 'contract_id' => null,
            'status' => 'awaiting_confirmation', 'total' => 100, 'paid' => 0,
            'delivery_address' => 'Kho', 'fulfillment_method' => 'pickup', 'requires_contract' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($sales)->get('/staff/work/sales_orders?q=&status=awaiting_confirmation')
            ->assertOk()->assertSee('DH-SEARCH-001');
        $this->get('/staff/work/sales_orders?q=NO-MATCH&status=awaiting_confirmation')
            ->assertOk()->assertSee('Chưa có đơn hàng phù hợp.')
            ->assertSee('href="'.route('staff.section', 'sales_orders').'"', false);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_contract_creation_keeps_form_values_open_after_validation_error(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'DH-CONTRACT-INPUT', 'customer_id' => $customer->id, 'status' => 'awaiting_contract',
            'total' => '100.00', 'paid' => '0.00', 'delivery_address' => 'Công trình thử',
            'fulfillment_method' => 'delivery', 'requires_contract' => true,
        ]);

        $this->actingAs($sales)->post('/staff/orders/'.$orderId.'/contracts', [
            'order_id' => $orderId, 'deposit_required' => '50.00',
            'payment_terms' => 'Điều khoản thanh toán cần giữ',
            'delivery_terms' => 'Điều khoản giao hàng cần giữ',
            'installments' => [[
                'sequence' => 1, 'label' => 'Đợt cọc cần giữ', 'amount' => '0',
                'trigger' => 'before_issue', 'required_before_issue' => '1',
            ]],
        ])->assertSessionHasErrors('installments.0.amount');

        $this->get('/staff/work/sales_orders')->assertOk()
            ->assertSee('DH-CONTRACT-INPUT')
            ->assertSee('Điều khoản thanh toán cần giữ')
            ->assertSee('Điều khoản giao hàng cần giữ')
            ->assertSee('name="installments[0][label]" value="Đợt cọc cần giữ"', false)
            ->assertSee('name="installments[0][amount]" value="0"', false);
    }

    public function test_contract_edit_keeps_schedule_and_terms_after_validation_error(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $contractId = DB::table('contracts')->insertGetId([
            'code' => 'HD-CONTRACT-EDIT-INPUT', 'created_by' => $sales->id, 'status' => 'draft',
            'total' => '100.00', 'deposit_required' => '0.00',
            'payment_terms' => 'Điều khoản thanh toán cũ', 'delivery_terms' => 'Điều khoản giao hàng cũ',
            'payment_schedule_draft' => json_encode([[
                'sequence' => 1, 'label' => 'Đợt cũ', 'amount' => '100.00', 'trigger' => 'before_issue',
                'due_on' => null, 'days_after_trigger' => null, 'required_before_issue' => true,
            ]]),
        ]);

        $this->actingAs($sales)->from('/staff/work/contracts')->put('/staff/contracts/'.$contractId, [
            'contract_id' => $contractId, 'deposit_required' => '50.00',
            'payment_terms' => 'Điều khoản thanh toán mới cần giữ',
            'delivery_terms' => 'Điều khoản giao hàng mới cần giữ',
            'installments' => [[
                'sequence' => 1, 'label' => 'Đợt mới cần giữ', 'amount' => '0',
                'trigger' => 'before_issue', 'required_before_issue' => '1',
            ]],
        ])->assertSessionHasErrors('installments.0.amount');

        $this->get('/staff/work/contracts')->assertOk()
            ->assertSee('HD-CONTRACT-EDIT-INPUT')
            ->assertSee('Điều khoản thanh toán mới cần giữ')
            ->assertSee('Điều khoản giao hàng mới cần giữ')
            ->assertSee('name="installments[0][label]" value="Đợt mới cần giữ"', false)
            ->assertSee('name="installments[0][amount]" value="0"', false);
    }

    public function test_rejection_requires_reason_releases_stock_and_is_visible_to_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $warehouseStaff = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'K2', 'name' => 'Kho chính']);
        $product = DB::table('products')->insertGetId(['code' => 'G-1', 'name' => 'Gạch', 'unit' => 'viên', 'price' => 1000, 'direct_purchase_allowed' => true]);
        DB::table('inventory')->insert(['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 2]);
        $workflow = app(CommerceWorkflow::class);
        $orderId = $workflow->placePickupOrder($customer->id, [$product => 2], $warehouse);

        $this->actingAs($warehouseStaff)->post('/staff/orders/'.$orderId.'/review', ['decision' => 'confirm'])->assertForbidden();
        $this->actingAs($sales)->post('/staff/orders/'.$orderId.'/review', ['decision' => 'reject', 'order_id' => $orderId])
            ->assertSessionHasErrors('reason');
        $this->get('/staff/work/sales_orders')->assertOk()->assertSee('invalid-feedback d-block', false);
        $longReason = str_repeat('x', 2001);
        $this->actingAs($sales)->post('/staff/orders/'.$orderId.'/review', [
            'decision' => 'reject', 'order_id' => $orderId, 'reason' => $longReason,
        ])->assertSessionHasErrors('reason')->assertSessionHasInput('reason', $longReason);
        $this->get('/staff/work/sales_orders')->assertOk()->assertSee('value="'.str_repeat('x', 40), false);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'awaiting_confirmation']);
        $orderCode = DB::table('orders')->where('id', $orderId)->value('code');
        $this->actingAs($sales)->post('/staff/orders/'.$orderId.'/review', ['decision' => 'reject', 'order_id' => $orderId, 'reason' => 'Cần xác minh số lượng'])
            ->assertRedirect()->assertSessionHas('success', 'Đơn '.$orderCode.' đã bị từ chối; trạng thái: Đã từ chối.');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'rejected', 'rejection_reason' => 'Cần xác minh số lượng']);
        $this->assertDatabaseHas('debts', ['order_id' => $orderId, 'amount' => 0, 'status' => 'settled']);
        $this->actingAs($customer)->get('/my/orders')->assertOk()->assertSee('Cần xác minh số lượng');
        $workflow->placePickupOrder($customer->id, [$product => 2], $warehouse);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_pickup_order_confirmation_respects_other_confirmed_reservations(): void
    {
        $customerA = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $customerB = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'K-RESERVE', 'name' => 'Kho giới hạn']);
        $product = DB::table('products')->insertGetId(['code' => 'G-RESERVE', 'name' => 'Gạch', 'unit' => 'viên', 'price' => 100, 'direct_purchase_allowed' => true]);
        DB::table('inventory')->insert(['warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => 3]);
        $workflow = app(CommerceWorkflow::class);
        $firstOrder = $workflow->placePickupOrder($customerA->id, [$product => 2], $warehouse);
        $secondOrder = $workflow->placePickupOrder($customerB->id, [$product => 2], $warehouse);

        $workflow->reviewOrder($firstOrder, $sales->id, true);
        try {
            $workflow->reviewOrder($secondOrder, $sales->id, true);
            $this->fail('Không được xác nhận hai đơn vượt tổng tồn kho.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('orders', ['id' => $secondOrder, 'status' => 'awaiting_confirmation']);
        }
    }

    public function test_legacy_unsigned_contract_can_still_be_signed_without_duplicate_order(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $product = DB::table('products')->insertGetId(['code' => 'G-OLD', 'name' => 'Gạch cũ', 'unit' => 'viên', 'price' => 100]);
        $quoteId = app(CommerceWorkflow::class)->requestQuote($customer->id, [$product => 2], 'Công trình cũ');
        DB::table('quotes')->where('id', $quoteId)->update(['status' => 'accepted']);
        $contractId = DB::table('contracts')->insertGetId([
            'code' => 'HD-OLD', 'quote_id' => $quoteId, 'created_by' => $sales->id,
            'status' => 'approved', 'total' => 200, 'deposit_required' => 0,
            'customer_confirmed_at' => now(),
        ]);

        $this->actingAs($customer)->get('/my/contracts')->assertOk()->assertSee('HD-OLD');
        $orderId = app(CommerceWorkflow::class)->signContract($contractId, 'contracts/old.pdf');
        $this->assertDatabaseHas('contracts', ['id' => $contractId, 'order_id' => $orderId, 'status' => 'signed']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'contract_id' => $contractId, 'requires_contract' => false]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_contract_requirement_backfill_preserves_pending_legacy_order_paths(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $attributes = [
            'customer_id' => $customer->id, 'status' => 'awaiting_confirmation', 'total' => 100,
            'delivery_address' => 'Địa chỉ cũ', 'requires_contract' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $deliveryOrder = DB::table('orders')->insertGetId($attributes + [
            'code' => 'DH-LEGACY-DELIVERY', 'fulfillment_method' => 'delivery',
        ]);
        $pickupOrder = DB::table('orders')->insertGetId($attributes + [
            'code' => 'DH-LEGACY-PICKUP', 'fulfillment_method' => 'pickup',
        ]);

        $migration = require base_path('database/migrations/2026_10_28_111829_backfill_contract_requirements_for_existing_orders.php');
        $migration->up();

        $this->assertDatabaseHas('orders', ['id' => $deliveryOrder, 'requires_contract' => true]);
        $this->assertDatabaseHas('orders', ['id' => $pickupOrder, 'requires_contract' => false]);
        $workflow = app(CommerceWorkflow::class);
        $workflow->reviewOrder($deliveryOrder, $sales->id, true);
        $workflow->reviewOrder($pickupOrder, $sales->id, true);
        $this->assertDatabaseHas('orders', ['id' => $deliveryOrder, 'status' => 'awaiting_contract']);
        $this->assertDatabaseHas('orders', ['id' => $pickupOrder, 'status' => 'awaiting_payment']);
    }
}
