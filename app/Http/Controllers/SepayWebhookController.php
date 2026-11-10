<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Payments\SepayWebhook;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * POST /webhooks/sepay/{token} — SePay gọi khi tài khoản ngân hàng có giao dịch.
 * Xác thực: token trong URL (ngẫu nhiên, theo tổ chức) + header "Authorization: Apikey <khóa>" (so sánh hằng thời gian).
 * Thành công phải trả 200 kèm {"success": true} (tài liệu SePay), kể cả khi giao dịch chưa khớp hóa đơn
 * — để SePay không gửi lại; giao dịch chưa khớp nằm trong danh sách cho kế toán xử lý.
 */
class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, SepayWebhook $webhook): JsonResponse
    {
        $orgId = SepayWebhook::organizationFor($token);
        if (! $orgId) {
            return response()->json(['success' => false], 404);
        }

        return app(CurrentOrganization::class)->run($orgId, function () use ($request, $webhook) {
            $key = Setting::secret('sepay_api_key');
            if (Setting::get('sepay_enabled') !== '1' || ! $key || ! hash_equals('Apikey '.$key, (string) $request->header('Authorization'))) {
                Log::warning('SePay webhook: từ chối yêu cầu không hợp lệ từ '.$request->ip());

                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            $v = Validator::make($request->all(), [
                'id' => ['required', 'integer', 'min:1'],
                'transferType' => ['required', 'in:in,out'],
                'transferAmount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
                'gateway' => ['nullable', 'string', 'max:100'],
                'accountNumber' => ['nullable', 'string', 'max:50'],
                'content' => ['nullable', 'string', 'max:2000'],
                'code' => ['nullable', 'string', 'max:100'],
                'referenceCode' => ['nullable', 'string', 'max:100'],
                'transactionDate' => ['nullable', 'string', 'max:30'],
            ]);
            if ($v->fails()) {
                return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
            }
            $webhook->handle($v->validated());

            return response()->json(['success' => true]);
        });
    }
}
