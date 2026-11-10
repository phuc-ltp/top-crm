<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Liên kết SePay (theo tổ chức, quyền settings.manage): bật/tắt, API Key (lưu mã hóa, không hiển thị lại),
 * địa chỉ webhook có token riêng, danh sách giao dịch nhận được.
 */
class SepaySettingsController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }

    public function edit(Request $request)
    {
        $this->authorizeManage($request);
        $status = $request->query('status');
        $token = Setting::get('sepay_webhook_token');

        return view('settings.sepay', [
            'enabled' => Setting::get('sepay_enabled') === '1',
            'hasKey' => (bool) Setting::secret('sepay_api_key'),
            'webhookUrl' => $token ? route('webhooks.sepay', $token) : null,
            'bankReady' => filled(Setting::get('bank_account_number')) && filled(Setting::get('bank_bin')),
            'status' => array_key_exists((string) $status, BankTransaction::statuses()) ? $status : null,
            'transactions' => BankTransaction::with('invoice:id,number')
                ->when(array_key_exists((string) $status, BankTransaction::statuses()), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate($this->perPage($request))->withQueryString(),
            'counts' => BankTransaction::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->all(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'sepay_enabled' => ['nullable', 'boolean'],
            'sepay_api_key' => ['nullable', 'string', 'min:16', 'max:200', 'regex:/^\S+$/'],
        ], [], ['sepay_api_key' => 'API Key']);
        $enabled = $request->boolean('sepay_enabled');
        $hasKey = filled($data['sepay_api_key'] ?? null) || Setting::secret('sepay_api_key');
        if ($enabled && ! $hasKey) {
            return back()->withErrors(['sepay_api_key' => __('Nhập API Key trước khi bật liên kết.')]);
        }

        $values = ['sepay_enabled' => $enabled ? '1' : '0'];
        if (filled($data['sepay_api_key'] ?? null)) {
            $values['sepay_api_key'] = Crypt::encryptString($data['sepay_api_key']);
        }
        if (! Setting::get('sepay_webhook_token')) {
            $values['sepay_webhook_token'] = Str::random(40);
        }
        Setting::put($values, 'payments');
        ActivityLogger::log('updated', null, ($enabled ? 'Bật' : 'Tắt').' liên kết SePay'.(isset($values['sepay_api_key']) ? ' (đổi API Key)' : ''));

        return redirect()->route('settings.sepay')->with('success', __('Đã lưu cài đặt SePay.'));
    }

    /** Đổi địa chỉ webhook (khi nghi bị lộ) — phải cập nhật lại trên my.sepay.vn. */
    public function regenerate(Request $request)
    {
        $this->authorizeManage($request);
        Setting::put(['sepay_webhook_token' => Str::random(40)], 'payments');
        ActivityLogger::log('updated', null, 'Đổi địa chỉ webhook SePay');

        return redirect()->route('settings.sepay')->with('warning', __('Đã tạo địa chỉ webhook mới — hãy cập nhật lại trong SePay, địa chỉ cũ không còn dùng được.'));
    }
}
