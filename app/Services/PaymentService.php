<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
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

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => __('Số tiền phải lớn hơn 0.')]);
            }
            if ($amount > $invoice->balance + 0.001) {
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
        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $total = (float) $invoice->total;

        $status = match (true) {
            $paid <= 0 => 'unpaid',
            $paid + 0.001 >= $total => 'paid',
            default => 'partial',
        };

        $invoice->forceFill(['amount_paid' => $paid, 'payment_status' => $status])->saveQuietly();
    }
}
