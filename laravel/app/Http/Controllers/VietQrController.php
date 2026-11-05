<?php

namespace App\Http\Controllers;

use App\Services\VietQrImageGenerator;
use App\Services\VietQrWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class VietQrController extends Controller
{
    public function accounts(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        return view('staff.vietqr-accounts', [
            'accounts' => DB::table('bank_accounts')->orderByDesc('id')->get(),
            'events' => DB::table('bank_account_events')->join('users', 'users.id', '=', 'bank_account_events.actor_id')
                ->select('bank_account_events.*', 'users.name as actor_name')->orderByDesc('bank_account_events.id')->limit(20)->get(),
        ]);
    }

    public function saveAccount(Request $request, VietQrWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $workflow->saveBankAccount(null, $this->accountData($request), $request->user()->id);

        return back()->with('success', 'Đã thêm tài khoản nhận tiền.');
    }

    public function updateAccount(Request $request, VietQrWorkflow $workflow, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $workflow->saveBankAccount($id, $this->accountData($request), $request->user()->id);

        return back()->with('success', 'Đã cập nhật tài khoản nhận tiền.');
    }

    private function accountData(Request $request): array
    {
        return $request->validate([
            'bank_bin' => ['required', 'regex:/^\d{6}$/'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_no' => ['required', 'regex:/^\d{6,19}$/'],
            'account_name' => ['required', 'string', 'max:100'],
            'active' => ['required', 'boolean'],
        ]);
    }

    public function staffIndex(Request $request, VietQrImageGenerator $imageGenerator): View
    {
        abort_unless($request->user()->role === 'accounting', 403);
        $activeDebtIds = DB::table('vietqr_requests')->whereIn('status', ['pending', 'claimed'])
            ->pluck('debt_id')->map(fn ($id) => (string) $id)->all();
        $debts = DB::table('debts')->leftJoin('orders', 'orders.id', '=', 'debts.order_id')
            ->leftJoin('contracts', 'contracts.id', '=', 'orders.contract_id')
            ->where('debts.direction', 'receivable')->where('debts.status', 'open')
            ->where(fn ($query) => $query->whereNull('orders.id')->orWhere(function ($orderQuery) {
                $orderQuery->whereNotIn('orders.status', ['awaiting_confirmation', 'awaiting_contract', 'rejected', 'cancelled'])
                    ->where(fn ($contractQuery) => $contractQuery->where('orders.requires_contract', false)
                        ->orWhere('contracts.status', 'signed'));
            }))->select('debts.*', 'orders.code as order_code')->orderByDesc('debts.id')->limit(100)->get()
            ->filter(fn ($debt) => bccomp((string) $debt->paid, (string) $debt->amount, 2) < 0
                && ! in_array((string) $debt->id, $activeDebtIds, true));
        $requests = DB::table('vietqr_requests')->join('debts', 'debts.id', '=', 'vietqr_requests.debt_id')
            ->join('users', 'users.id', '=', 'vietqr_requests.customer_id')
            ->select('vietqr_requests.*', 'debts.code as debt_code', 'users.name as customer_name')
            ->orderByDesc('vietqr_requests.id')->limit(100)->get();
        $this->attachQrImages($requests, $imageGenerator);

        return view('staff.vietqr', [
            'accounts' => DB::table('bank_accounts')->where('active', true)->orderByDesc('id')->get(),
            'debts' => $debts, 'requests' => $requests,
        ]);
    }

    public function create(Request $request, VietQrWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->role === 'accounting', 403);
        $data = $request->validate([
            'debt_id' => ['required', 'integer', 'exists:debts,id'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'amount' => ['required', 'regex:/^[1-9]\d{0,12}$/'],
        ]);
        $workflow->createRequest((int) $data['debt_id'], (int) $data['bank_account_id'], $data['amount'], $request->user()->id);

        return back()->with('success', 'Đã tạo yêu cầu VietQR. Chưa ghi nhận tiền vào công nợ.');
    }

    public function customerIndex(Request $request, VietQrImageGenerator $imageGenerator): View
    {
        abort_unless($request->user()->role === 'customer', 403);
        $requests = DB::table('vietqr_requests')->join('debts', 'debts.id', '=', 'vietqr_requests.debt_id')
            ->where('vietqr_requests.customer_id', $request->user()->id)
            ->select('vietqr_requests.*', 'debts.code as debt_code')
            ->orderByDesc('vietqr_requests.id')->get();
        $this->attachQrImages($requests, $imageGenerator);

        return view('store.transfers', compact('requests'));
    }

    private function attachQrImages(iterable $requests, VietQrImageGenerator $imageGenerator): void
    {
        foreach ($requests as $transfer) {
            if (! in_array($transfer->status, ['pending', 'claimed'], true)) {
                continue;
            }

            try {
                $transfer->qr_data_uri = $imageGenerator->generate(
                    (string) $transfer->bank_bin,
                    (string) $transfer->account_no,
                    (string) (int) $transfer->amount,
                    (string) $transfer->transfer_content,
                );
            } catch (Throwable $exception) {
                Log::warning('Unable to generate a VietQR image for a transfer request.', [
                    'request_id' => $transfer->id,
                    'exception' => $exception::class,
                ]);
                $transfer->qr_data_uri = null;
            }
        }
    }

    public function claim(Request $request, VietQrWorkflow $workflow, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'customer', 403);
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        $workflow->markTransferred($id, $request->user()->id, $data['reference'] ?? null);

        return back()->with('success', 'Đã báo chuyển khoản. Kế toán sẽ đối chiếu tiền thực nhận.');
    }

    public function confirm(Request $request, VietQrWorkflow $workflow, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'accounting', 403);
        $data = $request->validate(['bank_reference' => ['required', 'string', 'max:100']]);
        $workflow->confirm($id, $request->user()->id, trim($data['bank_reference']));

        return back()->with('success', 'Đã xác nhận tiền vào tài khoản và cập nhật công nợ.');
    }

    public function cancel(Request $request, VietQrWorkflow $workflow, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'accounting', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $workflow->cancel($id, $data['reason']);

        return back()->with('success', 'Đã hủy yêu cầu VietQR; công nợ không thay đổi.');
    }
}
