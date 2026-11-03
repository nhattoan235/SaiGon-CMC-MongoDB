<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class StaffSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_navigation_matches_account_role(): void
    {
        $staff = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($staff)->get('/staff')->assertOk()
            ->assertSee('Tổng quan | Sài Gòn CMC')->assertSee('Tài khoản nội bộ')
            ->assertSee('Không có hồ sơ chờ phê duyệt.')->assertDontSee('Báo giá của tôi');
        $this->actingAs($staff)->get('/staff/work/finance')->assertOk()
            ->assertSee('staff-workspace-scroll')->assertSee('Danh sách và thao tác Tài chính &amp; công nợ', false);

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $this->actingAs($customer)->get('/')->assertOk()
            ->assertSee('Báo giá của tôi')->assertDontSee('href="'.route('staff.dashboard').'"', false);
    }

    public function test_every_staff_role_can_open_only_its_work_sections(): void
    {
        $sections = [
            'quotes' => ['sales', 'manager', 'admin'],
            'contracts' => ['sales', 'manager', 'admin'],
            'sales_orders' => ['sales', 'manager', 'admin'],
            'finance' => ['accounting', 'manager', 'admin'],
            'purchases' => ['warehouse', 'manager', 'admin'],
            'orders' => ['warehouse', 'delivery', 'accounting', 'manager', 'admin'],
            'returns' => ['warehouse', 'delivery', 'accounting', 'manager', 'admin'],
            'inventory' => ['warehouse', 'manager', 'admin'],
        ];
        foreach (['sales', 'warehouse', 'accounting', 'manager', 'admin', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get('/staff')->assertOk();
            foreach ($sections as $section => $allowedRoles) {
                $response = $this->actingAs($user)->get('/staff/work/'.$section);
                in_array($role, $allowedRoles, true) ? $response->assertOk() : $response->assertForbidden();
            }
        }
    }

    public function test_only_admin_can_manage_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $fields = ['name' => 'Nhân viên thử', 'username' => 'qa_new_staff', 'role' => 'warehouse', 'password' => 'TestPass2026!'];

        $this->actingAs($sales)->post('/management/users', $fields)->assertForbidden();
        $this->actingAs($admin)->post('/management/users', $fields)->assertRedirect();
        $created = User::where('username', 'qa_new_staff')->firstOrFail();
        $this->assertTrue(Hash::check('TestPass2026!', $created->password));
        $this->actingAs($admin)->put('/management/users/'.$created->id, ['role' => 'warehouse', 'status' => 'disabled'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $created->id, 'status' => 'disabled']);
        $this->actingAs($admin)->put('/management/users/'.$admin->id, ['role' => 'sales', 'status' => 'active'])->assertStatus(422);
    }

    public function test_staff_sees_only_work_pages_allowed_for_their_role(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $this->actingAs($sales)->get('/staff/work/quotes')->assertOk()->assertSee('Báo giá');
        $this->actingAs($sales)->get('/staff/work/finance')->assertForbidden();

        $delivery = User::factory()->create(['role' => 'delivery', 'status' => 'active']);
        $this->actingAs($delivery)->get('/staff')->assertOk()
            ->assertSee('Chuyến hàng cần theo dõi')->assertSee('Chờ nhận hàng')
            ->assertSee('Không có chuyến hàng cần xử lý.');
        $this->actingAs($delivery)->get('/staff/work/orders')->assertOk()->assertSee('Xuất kho & giao nhận');
        $this->actingAs($delivery)->get('/staff/work/inventory')->assertForbidden();
        $this->actingAs($delivery)->get('/staff/work/unknown')->assertNotFound();

        $warehouse = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $this->actingAs($warehouse)->get('/staff/work/purchases')->assertOk()->assertSee('Hàng giao thiếu hoặc hỏng');
        $this->actingAs($warehouse)->get('/staff/work/orders')->assertOk()->assertSee('Xuất kho &amp; giao nhận', false);

        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $this->actingAs($accounting)->get('/staff')->assertOk()
            ->assertSee('Tình hình kế toán')->assertSee('Phải thu chưa tất toán')
            ->assertSee('Hóa đơn cần lập')->assertSee('Không có công nợ chưa tất toán.');
        $this->actingAs($accounting)->get('/staff/work/finance')->assertOk()->assertSee('Lịch quyết toán công nợ');

        $this->actingAs($sales)->post('/staff/debts/1/schedule')->assertForbidden();
        $this->actingAs($sales)->post('/staff/supplier-returns/1/settle')->assertForbidden();
        $this->actingAs($sales)->post('/staff/customer-credits/1/use')->assertForbidden();
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $this->actingAs($manager)->get('/staff')->assertOk()
            ->assertSee('Tổng quan | Sài Gòn CMC')->assertSee('Việc chờ phê duyệt')
            ->assertSee('Không có hồ sơ chờ phê duyệt.');
        $this->actingAs($manager)->get('/staff/work/contracts')->assertOk()
            ->assertSee('contract-empty')->assertSee('Chưa có hợp đồng.');
        $this->actingAs($manager)->get('/staff/work/finance')->assertOk()->assertSee('Tiền khách được hoàn hoặc cấn trừ');
        $this->actingAs($manager)->post('/staff/customer-credits/1/use')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/staff/work/finance')->assertOk()->assertSee('Tiền khách được hoàn hoặc cấn trừ');
        $this->actingAs($admin)->post('/staff/customer-credits/1/use')->assertSessionHasErrors(['type', 'amount']);
        $this->actingAs($sales)->post('/staff/purchases/1/incidents')->assertForbidden();
        $this->actingAs($delivery)->post('/staff/contracts/1/cancel')->assertForbidden();
        $this->actingAs($delivery)->post('/staff/delivery-runs')->assertForbidden();
    }

    public function test_sales_dashboard_counts_all_actionable_quotes_and_links_to_the_matching_filter(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (range(1, 35) as $number) {
            DB::table('quotes')->insert([
                'code' => sprintf('BG-OVERVIEW-%03d', $number), 'customer_id' => $customer->id,
                'status' => 'requested', 'delivery_address' => 'Công trình thử',
            ]);
        }

        $this->actingAs($sales)->get('/staff')->assertOk()
            ->assertSee('>35<', false)
            ->assertSee(route('staff.section', ['section' => 'quotes', 'status' => 'actionable']), false);
    }

    public function test_contract_dashboard_filter_shows_actionable_contracts_only(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        foreach ([['HD-ACTIONABLE', 'pending_approval'], ['HD-DRAFT', 'draft'], ['HD-SIGNED', 'signed']] as [$code, $status]) {
            DB::table('contracts')->insert([
                'code' => $code, 'created_by' => $sales->id, 'status' => $status, 'total' => '100.00',
            ]);
        }

        $this->actingAs($sales)->get('/staff/work/contracts?status=actionable')->assertOk()
            ->assertSee('HD-ACTIONABLE')->assertSee('HD-DRAFT')->assertDontSee('HD-SIGNED');
    }

    public function test_accounting_dashboard_filters_unsettled_payables_and_orders_without_invoices(): void
    {
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $supplier = DB::table('suppliers')->insertGetId(['code' => 'NCC-DASH-FILTER', 'name' => 'Nhà cung cấp thử']);
        DB::table('debts')->insert([
            'code' => 'CN-DASH-OPEN', 'direction' => 'payable', 'supplier_id' => $supplier,
            'amount' => '100.00', 'paid' => '20.00', 'status' => 'open',
        ]);
        DB::table('debts')->insert([
            'code' => 'CN-DASH-SETTLED', 'direction' => 'payable', 'supplier_id' => $supplier,
            'amount' => '100.00', 'paid' => '100.00', 'status' => 'settled',
        ]);

        $this->actingAs($accounting)->get('/staff/work/finance?direction=payable&status=unsettled')->assertOk()
            ->assertSee('CN-DASH-OPEN')->assertDontSee('CN-DASH-SETTLED')
            ->assertSee('khoản chưa tất toán')->assertSee('· phải trả')
            ->assertDontSee("@elseif(request('view') === 'pending-invoices')", false);

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        DB::table('orders')->insert([
            'code' => 'DH-DASH-INVOICE', 'customer_id' => $customer->id, 'status' => 'ready_to_issue',
            'total' => '100.00', 'paid' => '100.00', 'delivery_address' => 'Kho nhận',
            'fulfillment_method' => 'pickup', 'requires_contract' => false,
        ]);
        DB::table('orders')->insert([
            'code' => 'DH-DASH-NOT-INVOICE', 'customer_id' => $customer->id, 'status' => 'awaiting_payment',
            'total' => '100.00', 'paid' => '0.00', 'delivery_address' => 'Kho nhận',
            'fulfillment_method' => 'pickup', 'requires_contract' => false,
        ]);

        $this->actingAs($accounting)->get('/staff/work/orders?view=pending-invoices')->assertOk()
            ->assertSee('DH-DASH-INVOICE')->assertDontSee('DH-DASH-NOT-INVOICE');
    }

    public function test_finance_list_identifies_counterparty_and_filters_settled_items(): void
    {
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Khách Hương Giang']);
        $supplier = DB::table('suppliers')->insertGetId(['code' => 'NCC-FINANCE-NAME', 'name' => 'Vật liệu An Phát']);
        DB::table('debts')->insert([
            'code' => 'CN-THU-NAME', 'direction' => 'receivable', 'customer_id' => $customer->id,
            'amount' => '150.00', 'paid' => '50.00', 'status' => 'open',
        ]);
        DB::table('debts')->insert([
            'code' => 'CN-CHI-NAME', 'direction' => 'payable', 'supplier_id' => $supplier,
            'amount' => '200.00', 'paid' => '0.00', 'status' => 'open',
        ]);
        DB::table('debts')->insert([
            'code' => 'CN-DA-THU-NAME', 'direction' => 'receivable', 'customer_id' => $customer->id,
            'amount' => '75.00', 'paid' => '75.00', 'status' => 'settled',
        ]);

        $this->actingAs($accounting)->get('/staff/work/finance?status=unsettled')->assertOk()
            ->assertSee('Khách Hương Giang')->assertSee('Vật liệu An Phát')
            ->assertSee('CN-THU-NAME')->assertSee('CN-CHI-NAME')->assertDontSee('CN-DA-THU-NAME')
            ->assertSee('Phải thu')->assertSee('Phải trả');

        $this->actingAs($accounting)->get('/staff/work/finance?direction=receivable&status=settled')->assertOk()
            ->assertSee('CN-DA-THU-NAME')->assertDontSee('CN-THU-NAME')->assertDontSee('CN-CHI-NAME');
    }

    public function test_sales_quote_filters_search_status_and_sort_records(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $firstCustomer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Khách Minh']);
        $secondCustomer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Khách An']);
        foreach ([
            ['code' => 'BG-TEST-01', 'customer_id' => $firstCustomer->id, 'status' => 'requested'],
            ['code' => 'BG-TEST-02', 'customer_id' => $secondCustomer->id, 'status' => 'draft'],
            ['code' => 'BG-TEST-03', 'customer_id' => $firstCustomer->id, 'status' => 'sent'],
        ] as $quote) {
            DB::table('quotes')->insert($quote + ['delivery_address' => 'Công trình thử']);
        }

        $this->actingAs($sales)->get('/staff')->assertOk()->assertSee('Cần lập báo giá');
        $this->actingAs($sales)->get('/staff/work/quotes?status=actionable')->assertOk()
            ->assertSee('BG-TEST-01')->assertSee('BG-TEST-02')->assertDontSee('BG-TEST-03');
        $this->actingAs($sales)->get('/staff/work/quotes?q=Khách+An')->assertOk()
            ->assertSee('BG-TEST-02')->assertDontSee('BG-TEST-01');
        $response = $this->actingAs($sales)->get('/staff/work/quotes?sort=oldest');
        $response->assertOk()->assertSeeInOrder(['BG-TEST-01', 'BG-TEST-02', 'BG-TEST-03']);
    }

    public function test_contract_search_paginates_all_matches_and_keeps_the_search_term(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (range(1, 35) as $number) {
            $quoteId = DB::table('quotes')->insertGetId([
                'code' => sprintf('BG-CONTRACT-PAGE-%03d', $number), 'customer_id' => $customer->id,
                'status' => 'accepted', 'delivery_address' => 'Công trình thử',
            ]);
            DB::table('contracts')->insert([
                'code' => sprintf('HD-PAGE-%03d', $number), 'quote_id' => $quoteId, 'created_by' => $sales->id,
                'status' => 'signed', 'total' => '100.00', 'deposit_required' => '0.00',
                'payment_terms' => 'Thanh toán theo thỏa thuận', 'delivery_terms' => 'Nhận tại kho',
            ]);
        }

        $url = '/staff/work/contracts?contract=HD-PAGE';
        $this->actingAs($sales)->get($url)->assertOk()->assertSee('HD-PAGE-035')->assertDontSee('HD-PAGE-001')
            ->assertSee('page=2', false)->assertSee('contract=HD-PAGE', false);
        $this->actingAs($sales)->get($url.'&page=2')->assertOk()
            ->assertSee('HD-PAGE-001')->assertDontSee('HD-PAGE-035');
    }

    public function test_finance_debt_list_paginates_past_the_old_thirty_row_limit(): void
    {
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (range(1, 35) as $number) {
            DB::table('debts')->insert([
                'code' => sprintf('CN-FINANCE-%03d', $number), 'direction' => 'receivable',
                'customer_id' => $customer->id, 'amount' => '100.00', 'paid' => '0.00', 'status' => 'open',
            ]);
        }

        $this->actingAs($accounting)->get('/staff/work/finance')->assertOk()
            ->assertSee('CN-FINANCE-035')->assertDontSee('CN-FINANCE-005')->assertSee('debts_page=2', false);
        $this->actingAs($accounting)->get('/staff/work/finance?debts_page=2')->assertOk()
            ->assertSee('CN-FINANCE-005')->assertDontSee('CN-FINANCE-035');
    }

    public function test_warehouse_order_queue_paginates_past_the_old_thirty_row_limit(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        foreach (range(1, 35) as $number) {
            DB::table('orders')->insert([
                'code' => sprintf('DH-QUEUE-%03d', $number), 'customer_id' => $customer->id,
                'contract_id' => null, 'status' => 'ready_to_issue', 'total' => '100.00', 'paid' => '100.00',
                'delivery_address' => 'Nhận tại kho', 'fulfillment_method' => 'pickup', 'requires_contract' => false,
            ]);
        }

        $this->actingAs($warehouse)->get('/staff/work/orders')->assertOk()
            ->assertSee('DH-QUEUE-035')->assertDontSee('DH-QUEUE-005')->assertSee('orders_page=2', false);
        $this->actingAs($warehouse)->get('/staff/work/orders?orders_page=2')->assertOk()
            ->assertSee('DH-QUEUE-005')->assertDontSee('DH-QUEUE-035');
    }

    public function test_sales_quote_search_paginates_all_matches_and_keeps_filters_on_later_pages(): void
    {
        $sales = User::factory()->create(['role' => 'sales', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active', 'name' => 'Khách phân trang']);
        foreach (range(1, 35) as $number) {
            DB::table('quotes')->insert([
                'code' => sprintf('BG-PAGE-%03d', $number), 'customer_id' => $customer->id,
                'status' => 'requested', 'delivery_address' => 'Công trình thử',
            ]);
        }

        $url = '/staff/work/quotes?q=BG-PAGE&status=requested';
        $this->actingAs($sales)->get($url)->assertOk()->assertSee('35 hồ sơ')
            ->assertSee('page=2', false)->assertSee('q=BG-PAGE', false)->assertSee('status=requested', false);
        $this->actingAs($sales)->get($url.'&page=2')->assertOk()
            ->assertSee('BG-PAGE-001')->assertDontSee('BG-PAGE-035')
            ->assertSee('value="requested" selected', false);
    }

    public function test_warehouse_inventory_separates_current_stock_from_movement_history(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);

        $this->actingAs($warehouse)->get('/staff')->assertOk()->assertSee('warehouse-metrics');
        $this->actingAs($warehouse)->get('/staff/work/inventory')->assertOk()
            ->assertSee('Tồn kho thực tế')->assertDontSee('Lịch sử nhập, xuất và điều chỉnh')
            ->assertDontSee('Lập phiếu kiểm kê');
        $this->actingAs($warehouse)->get('/staff/work/inventory?view=stocktakes')->assertOk()
            ->assertSee('Lập phiếu kiểm kê')->assertDontSee('Lịch sử nhập, xuất và điều chỉnh');
        $this->actingAs($warehouse)->get('/staff/work/inventory?view=movements')->assertOk()
            ->assertSee('Lịch sử nhập, xuất và điều chỉnh')->assertDontSee('Lập phiếu kiểm kê');
        $this->actingAs($warehouse)->get('/staff/work/returns')->assertOk()
            ->assertSee('Danh sách yêu cầu đổi trả')->assertSee('Không có yêu cầu đổi trả');
    }

    public function test_purchase_details_show_supplier_destination_and_received_quantities(): void
    {
        $warehouseStaff = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $supplier = DB::table('suppliers')->insertGetId(['code' => 'NCC-QOL', 'name' => 'Nhà cung cấp Minh Tâm']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'KHO-QOL', 'name' => 'Kho Thủ Đức']);
        $product = DB::table('products')->insertGetId(['code' => 'GACH-QOL', 'name' => 'Gạch đỏ', 'unit' => 'viên', 'price' => 100]);
        $purchase = DB::table('purchase_orders')->insertGetId([
            'code' => 'PN-QOL-001', 'supplier_id' => $supplier, 'warehouse_id' => $warehouse,
            'requested_by' => $warehouseStaff->id, 'status' => 'approved', 'total' => '500.00',
        ]);
        DB::table('purchase_items')->insert([
            'purchase_order_id' => $purchase, 'product_id' => $product,
            'quantity' => 5, 'received_quantity' => 2, 'unit_price' => '100.00',
        ]);

        $this->actingAs($warehouseStaff)->get('/staff/work/purchases')->assertOk()
            ->assertSee('PN-QOL-001')->assertSee('Nhà cung cấp Minh Tâm')->assertSee('Kho Thủ Đức')
            ->assertSee('Gạch đỏ · viên')->assertSee('Đã nhận')->assertSee('Còn nhận');
    }

    public function test_purchase_proposal_form_keeps_values_and_field_error_after_validation_failure(): void
    {
        $warehouseStaff = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $supplier = DB::table('suppliers')->insertGetId(['code' => 'NCC-KEEP', 'name' => 'Nhà cung cấp']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'KHO-KEEP', 'name' => 'Kho nhận']);
        $product = DB::table('products')->insertGetId(['code' => 'SP-KEEP', 'name' => 'Xi măng', 'unit' => 'bao', 'price' => 10]);

        $this->actingAs($warehouseStaff)->from('/staff/work/purchases')->post('/staff/purchases', [
            'supplier_id' => $supplier, 'warehouse_id' => $warehouse,
            'items' => [['product_id' => $product, 'quantity' => '0', 'unit_price' => '12.5']],
        ])->assertSessionHasErrors(['items.0.quantity'])->assertRedirect('/staff/work/purchases');

        $this->get('/staff/work/purchases')->assertOk()
            ->assertSee('value="0"', false)->assertSee('value="12.5"', false);
    }

    public function test_stocktake_form_keeps_count_and_reason_after_validation_failure(): void
    {
        $warehouseStaff = User::factory()->create(['role' => 'warehouse', 'status' => 'active']);
        $warehouse = DB::table('warehouses')->insertGetId(['code' => 'KHO-ST-KEEP', 'name' => 'Kho kiểm kê']);
        $product = DB::table('products')->insertGetId(['code' => 'SP-ST-KEEP', 'name' => 'Thép', 'unit' => 'kg', 'price' => 10]);

        $this->actingAs($warehouseStaff)->from('/staff/work/inventory?view=stocktakes')->post('/staff/stocktakes', [
            'warehouse_id' => $warehouse, 'product_id' => $product, 'quantity' => '7', 'reason' => '',
        ])->assertSessionHasErrors(['reason'])->assertRedirect('/staff/work/inventory?view=stocktakes');

        $this->get('/staff/work/inventory?view=stocktakes')->assertOk()
            ->assertSee('value="7"', false)->assertSee('Lý do chênh lệch');
    }
}
