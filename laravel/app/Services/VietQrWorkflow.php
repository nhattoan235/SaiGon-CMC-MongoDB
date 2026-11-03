<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VietQrWorkflow
{
    public function saveBankAccount(?int $id, array $data, int $adminId): int
    {
        return DB::transaction(function () use ($id, $data, $adminId) {
            $before = $id ? DB::table('bank_accounts')->where('id', $id)->lockForUpdate()->first() : null;
            if ($id && ! $before) {
                $this->fail('Tài khoản ngân hàng không tồn tại.');
            }
            $values = [
                'bank_bin' => $data['bank_bin'], 'bank_name' => $data['bank_name'],
                'account_no' => $data['account_no'], 'account_name' => $data['account_name'],
                'active' => (int) $data['active'], 'updated_by' => $adminId, 'updated_at' => now(),
            ];
            if ($id) {
                DB::table('bank_accounts')->where('id', $id)->update($values);
            } else {
                $values['created_by'] = $adminId;
                $values['created_at'] = now();
                $id = DB::table('bank_accounts')->insertGetId($values);
            }
            DB::table('bank_account_events')->insert([
                'bank_account_id' => $id, 'actor_id' => $adminId,
                'action' => $before ? 'updated' : 'created',
                'before_data' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'after_data' => json_encode(DB::table('bank_accounts')->find($id), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $id;
        });
    }

    public function createRequest(int $debtId, int $accountId, string $amount, int $accountantId): int
    {
        return DB::transaction(function () use ($debtId, $accountId, $amount, $accountantId) {
            $debt = DB::table('debts')->where('id', $debtId)->lockForUpdate()->first();
            $account = DB::table('bank_accounts')->where('id', $accountId)->first();
            if (! $debt || $debt->direction !== 'receivable' || ! $debt->customer_id || ! $account || ! $account->active) {
                $this->fail('Công nợ hoặc tài khoản nhận tiền không hợp lệ.');
            }
            if (! preg_match('/^[1-9]\d{0,12}$/', $amount) || bccomp($amount, bcsub((string) $debt->amount, (string) $debt->paid, 2), 2) > 0) {
                $this->fail('Số tiền QR phải là số nguyên VND và không vượt công nợ còn lại.');
            }
            if (DB::table('vietqr_requests')->where('debt_id', $debtId)->whereIn('status', ['pending', 'claimed'])->exists()) {
                $this->fail('Công nợ này đã có yêu cầu chuyển khoản đang chờ.');
            }
            if ($debt->order_id) {
                $order = DB::table('orders')->find($debt->order_id);
                $contract = $order?->contract_id ? DB::table('contracts')->find($order->contract_id) : null;
                if (! $order || in_array($order->status, ['awaiting_confirmation', 'awaiting_contract', 'rejected', 'cancelled'], true)
                    || ($order->requires_contract && $contract?->status !== 'signed')) {
                    $this->fail('Đơn hàng chưa đủ điều kiện thanh toán.');
                }
            }
            $id = DB::table('vietqr_requests')->insertGetId([
                'code' => 'TEMP-'.uniqid(), 'debt_id' => $debtId, 'customer_id' => $debt->customer_id,
                'bank_account_id' => $accountId, 'bank_bin' => $account->bank_bin,
                'bank_name' => $account->bank_name, 'account_no' => $account->account_no,
                'account_name' => $account->account_name, 'amount' => $amount,
                'transfer_content' => 'TEMP-'.uniqid(), 'status' => 'pending',
                'created_by' => $accountantId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('vietqr_requests')->where('id', $id)->update([
                'code' => 'QR-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'transfer_content' => 'CMC'.str_pad((string) $id, 9, '0', STR_PAD_LEFT),
            ]);

            return $id;
        });
    }

    public function markTransferred(int $requestId, int $customerId, ?string $reference): void
    {
        DB::transaction(function () use ($requestId, $customerId, $reference) {
            $request = DB::table('vietqr_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request || (int) $request->customer_id !== $customerId) {
                abort(404);
            }
            if ($request->status !== 'pending' && $request->status !== 'claimed') {
                $this->fail('Yêu cầu này đã kết thúc.');
            }
            DB::table('vietqr_requests')->where('id', $requestId)->update([
                'status' => 'claimed', 'claimed_at' => $request->claimed_at ?? now(),
                'customer_reference' => $reference, 'updated_at' => now(),
            ]);
        });
    }

    public function confirm(int $requestId, int $accountantId, string $bankReference): int
    {
        return DB::transaction(function () use ($requestId, $accountantId, $bankReference) {
            $request = DB::table('vietqr_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request || ! in_array($request->status, ['pending', 'claimed'], true)) {
                $this->fail('Yêu cầu chuyển khoản không còn chờ xác nhận.');
            }
            if (DB::table('vietqr_requests')->where('bank_reference', $bankReference)->exists()
                || DB::table('payments')->where('method', 'bank_transfer')->where('reference', $bankReference)->exists()) {
                $this->fail('Mã giao dịch ngân hàng đã được xác nhận trước đó.');
            }
            $paymentId = app(CommerceWorkflow::class)->recordPayment(
                (int) $request->debt_id, $accountantId, (string) $request->amount,
                'bank_transfer', $bankReference, 'vietqr-request-'.$requestId
            );
            DB::table('vietqr_requests')->where('id', $requestId)->update([
                'status' => 'confirmed', 'confirmed_by' => $accountantId,
                'confirmed_at' => now(), 'payment_id' => $paymentId,
                'bank_reference' => $bankReference, 'updated_at' => now(),
            ]);

            return $paymentId;
        });
    }

    public function cancel(int $requestId, string $reason): void
    {
        DB::transaction(function () use ($requestId, $reason) {
            $request = DB::table('vietqr_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request || ! in_array($request->status, ['pending', 'claimed'], true)) {
                $this->fail('Yêu cầu chuyển khoản không còn chờ xử lý.');
            }
            DB::table('vietqr_requests')->where('id', $requestId)->update([
                'status' => 'cancelled', 'rejected_reason' => $reason,
                'cancelled_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
