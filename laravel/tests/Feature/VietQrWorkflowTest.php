<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommerceWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\RefreshDocumentDatabase as RefreshDatabase;
use Tests\TestCase;

class VietQrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupActors(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $accountant = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $debtId = DB::table('debts')->insertGetId([
            'code' => 'CN-VIETQR', 'direction' => 'receivable', 'customer_id' => $customer->id,
            'amount' => '1250.00', 'paid' => '0.00', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $accountData = [
            'bank_bin' => '970415', 'bank_name' => 'VietinBank',
            'account_no' => '1234567890', 'account_name' => 'SAI GON CMC', 'active' => 1,
        ];
        $this->actingAs($admin)->post(route('management.bank-accounts.store'), $accountData)->assertRedirect();

        return [$admin, $accountant, $customer, $other, $debtId, $accountData];
    }

    public function test_vietqr_waits_for_bank_confirmation_before_recording_payment(): void
    {
        [$admin, $accountant, $customer, $other, $debtId] = $this->setupActors();
        $accountId = DB::table('bank_accounts')->value('id');
        $this->actingAs($accountant)->get(route('management.bank-accounts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('staff.vietqr.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('staff.vietqr.index'))->assertForbidden();
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => $accountId, 'amount' => '1250',
        ])->assertRedirect();
        $transfer = DB::table('vietqr_requests')->first();
        $this->assertSame('pending', $transfer->status);
        $this->assertSame('CMC000000001', $transfer->transfer_content);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', (string) DB::table('debts')->find($debtId)->paid);

        $this->actingAs($admin)->get(route('my.transfers.index'))->assertForbidden();
        $this->actingAs($other)->get(route('my.transfers.index'))->assertDontSee($transfer->code);
        $this->actingAs($other)->post(route('my.transfers.claim', $transfer->id))->assertNotFound();
        $this->actingAs($customer)->get(route('my.transfers.index'))
            ->assertOk()->assertSee($transfer->transfer_content)->assertSee('img.vietqr.io');
        $this->actingAs($customer)->post(route('my.transfers.claim', $transfer->id), ['reference' => 'KH-001'])->assertRedirect();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('vietqr_requests', ['id' => $transfer->id, 'status' => 'claimed']);

        $this->actingAs($admin)->post(route('staff.vietqr.confirm', $transfer->id), ['bank_reference' => 'BANK-001'])->assertForbidden();
        $this->actingAs($accountant)->post(route('staff.vietqr.confirm', $transfer->id), ['bank_reference' => 'BANK-001'])->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['debt_id' => $debtId, 'method' => 'bank_transfer', 'reference' => 'BANK-001']);
        $this->assertDatabaseHas('debts', ['id' => $debtId, 'status' => 'settled', 'paid' => '1250.00']);
        $this->assertDatabaseHas('vietqr_requests', ['id' => $transfer->id, 'status' => 'confirmed']);
        $this->actingAs($accountant)->post(route('staff.vietqr.confirm', $transfer->id), ['bank_reference' => 'BANK-001'])->assertSessionHasErrors();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cancelling_request_keeps_debt_unchanged_and_account_changes_are_locked_while_pending(): void
    {
        [$admin, $accountant, $customer, $other, $debtId, $accountData] = $this->setupActors();
        $accountId = DB::table('bank_accounts')->value('id');
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => $accountId, 'amount' => '500',
        ])->assertRedirect();
        $requestId = DB::table('vietqr_requests')->value('id');
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => $accountId, 'amount' => '500',
        ])->assertSessionHasErrors();
        $this->actingAs($admin)->put(route('management.bank-accounts.update', $accountId), array_merge($accountData, ['active' => 0]))->assertSessionHasErrors();
        $this->actingAs($accountant)->post(route('staff.vietqr.cancel', $requestId), ['reason' => 'Sai số tiền'])->assertRedirect();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('debts', ['id' => $debtId, 'paid' => '0.00']);
        $this->actingAs($admin)->put(route('management.bank-accounts.update', $accountId), array_merge($accountData, ['active' => 0]))->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', ['id' => $accountId, 'active' => 0]);
    }

    public function test_direct_bank_transfer_for_receivable_is_rejected(): void
    {
        [$admin, $accountant, $customer, $other, $debtId] = $this->setupActors();
        $this->actingAs($accountant)->post(route('staff.payments.record', $debtId), [
            'amount' => '1250', 'method' => 'bank_transfer',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('method');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_confirmation_refuses_to_double_count_money_paid_by_another_method(): void
    {
        [$admin, $accountant, $customer, $other, $debtId] = $this->setupActors();
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => DB::table('bank_accounts')->value('id'), 'amount' => '1250',
        ])->assertRedirect();
        $requestId = DB::table('vietqr_requests')->value('id');
        app(CommerceWorkflow::class)->recordPayment($debtId, $accountant->id, '1250', 'cash');

        $this->actingAs($accountant)->post(route('staff.vietqr.confirm', $requestId), [
            'bank_reference' => 'BANK-LATE',
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('vietqr_requests', ['id' => $requestId, 'status' => 'pending', 'payment_id' => null]);
        $this->assertDatabaseHas('debts', ['id' => $debtId, 'paid' => '1250.00']);
    }

    public function test_confirmed_transfer_releases_an_eligible_order_through_the_existing_payment_schedule(): void
    {
        [$admin, $accountant, $customer, $other, $debtId] = $this->setupActors();
        $orderId = DB::table('orders')->insertGetId([
            'code' => 'DH-VIETQR', 'customer_id' => $customer->id,
            'delivery_address' => 'Công trình kiểm thử', 'total' => '1250.00',
            'paid' => '0.00', 'status' => 'awaiting_payment', 'requires_contract' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('debts')->where('id', $debtId)->update(['order_id' => $orderId]);
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => DB::table('bank_accounts')->value('id'), 'amount' => '1250',
        ])->assertRedirect();
        $requestId = DB::table('vietqr_requests')->value('id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'awaiting_payment', 'paid' => '0.00']);

        $this->actingAs($accountant)->post(route('staff.vietqr.confirm', $requestId), [
            'bank_reference' => 'BANK-ORDER-001',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'ready_to_issue', 'paid' => '1250.00']);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_bank_reference_already_recorded_on_an_older_payment_cannot_be_confirmed_again(): void
    {
        [$admin, $accountant, $customer, $other, $debtId] = $this->setupActors();
        app(CommerceWorkflow::class)->recordPayment($debtId, $accountant->id, '250', 'bank_transfer', 'BANK-USED');
        $this->actingAs($accountant)->post(route('staff.vietqr.create'), [
            'debt_id' => $debtId, 'bank_account_id' => DB::table('bank_accounts')->value('id'), 'amount' => '1000',
        ])->assertRedirect();
        $requestId = DB::table('vietqr_requests')->value('id');

        $this->actingAs($accountant)->post(route('staff.vietqr.confirm', $requestId), [
            'bank_reference' => 'BANK-USED',
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('debts', ['id' => $debtId, 'paid' => '250.00']);
        $this->assertDatabaseHas('vietqr_requests', ['id' => $requestId, 'status' => 'pending']);
    }
}
