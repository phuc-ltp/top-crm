<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function record(Invoice $invoice, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== 'issued') {
                throw ValidationException::withMessages(['amount' => __('Chỉ ghi nhận thanh toán cho hóa đơn đã phát hành.')]);
            }

            $amount = Decimal::round(is_numeric($data['amount']) ? (string) $data['amount'] : 0);
            if (Decimal::cmp($amount, 0) <= 0) {
                throw ValidationException::withMessages(['amount' => __('Số tiền phải lớn hơn 0.')]);
            }
            if (Decimal::cmp($amount, Decimal::sub($invoice->total, $invoice->amount_paid)) > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Số tiền vượt quá số còn phải thu (:amount).', ['amount' => number_format($invoice->balance, 0, ',', '.').' ₫']),
                ]);
            }

            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'payment_date' => $data['payment_date'] ?? today(),
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
                'is_demo' => $invoice->is_demo,
            ]);

            $this->refreshInvoice($invoice);

            return $payment;
        });
    }

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $payment->delete();
            $this->refreshInvoice($invoice);
        });
    }

    /** Tính lại số đã thu và trạng thái thanh toán từ bảng payments. */
    public function refreshInvoice(Invoice $invoice): void
    {
        $paid = Decimal::round($invoice->payments()->sum('amount'));

        $status = match (true) {
            Decimal::cmp($paid, 0) <= 0 => 'unpaid',
            Decimal::cmp($paid, $invoice->total) >= 0 => 'paid',
            default => 'partial',
        };

        $invoice->forceFill(['amount_paid' => $paid, 'payment_status' => $status])->saveQuietly();
    }
}
