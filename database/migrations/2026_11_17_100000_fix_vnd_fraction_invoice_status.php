<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hóa đơn VND đã thu đủ phần chẵn nhưng còn phần lẻ < 0,5 đồng (tổng có xu, VD 102.599.897,40 ₫) bị kẹt ở
 * "thanh toán một phần" và form ghi nhận báo "vượt quá số còn phải thu (0 ₫)". Chỉ đổi payment_status → paid;
 * KHÔNG đổi số tiền. Tổ chức dùng ngoại tệ không bị ảnh hưởng.
 */
return new class extends Migration
{
    public function up(): void
    {
        $foreign = DB::table('organization_settings')->where('key', 'currency')->where('value', '!=', 'VND')->pluck('organization_id')->all();

        DB::table('invoices')->where('status', 'issued')->where('payment_status', '!=', 'paid')
            ->whereNull('deleted_at')->whereNotIn('organization_id', $foreign)
            ->whereRaw('total - credited_amount - amount_paid > 0')
            ->whereRaw('total - credited_amount - amount_paid < 0.5')
            ->where('amount_paid', '>', 0)
            ->update(['payment_status' => 'paid']);
    }

    public function down(): void
    {
        // Trạng thái thanh toán là dữ liệu suy ra (PaymentService::refreshInvoice tính lại) — không cần hoàn tác.
    }
};
