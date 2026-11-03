<?php

namespace App\Http\Controllers;

use App\Services\VietQrWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VietQrController extends Controller
{
    public function accounts(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);
        $accounts = DB::table('bank_accounts')->orderByDesc('id')->get();
        foreach ($accounts as $account) {
            if ($account->active) {
                $account->qr_url = $this->bankAccountQrUrl($account);
            }
        }

        return view('staff.vietqr-accounts', [
            'accounts' => $accounts,
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

    public function staffIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'accounting', 403);

        return view('staff.vietqr', ['accounts' => $this->activeAccounts()]);
    }

    public function customerIndex(Request $request): View
    {
        abort_unless($request->user()->role === 'customer', 403);

        return view('store.transfers', ['accounts' => $this->activeAccounts()]);
    }

    private function activeAccounts()
    {
        $accounts = DB::table('bank_accounts')->where('active', true)->orderByDesc('id')->get();
        foreach ($accounts as $account) {
            $account->qr_url = $this->bankAccountQrUrl($account);
        }

        return $accounts;
    }

    private function bankAccountQrUrl(object $account): string
    {
        $accountName = Str::upper(Str::ascii($account->account_name));
        $params = preg_match('/^[A-Z0-9 ]{5,50}$/', $accountName) ? ['accountName' => $accountName] : [];

        return $this->vietQrImageUrl($account->bank_bin, $account->account_no, $params);
    }

    /** @param array<string, int|string> $params */
    private function vietQrImageUrl(string $bankBin, string $accountNo, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return 'https://img.vietqr.io/image/'.$bankBin.'-'.$accountNo.'-compact2.svg'.($query === '' ? '' : '?'.$query);
    }

}
