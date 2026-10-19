<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Thanh toán, hoàn tiền, phiếu giảm trừ của hóa đơn — mọi số tiền tính bằng số thập phân chính xác, trong giao dịch
 * có khóa dòng hóa đơn.
 *  - Đã thu (amount_paid) = Σ thanh toán − Σ hoàn tiền (hoàn tiền lưu là khoản thanh toán âm loại "refund",
 *    nên doanh thu theo dòng tiền tự trừ phần hoàn).
 *  - Còn phải thu = tổng hóa đơn − giảm trừ − đã thu.
 */
class PaymentService
{
    public function record(Invoice $invoice, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = $this->lock($invoice);
            $amount = $this->amount($data['amount']);
            if (Decimal::cmp($amount, self::due($invoice)) > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Số tiền vượt quá số còn phải thu (:amount).', ['amount' => money(self::due($invoice))]),
                ]);
            }
            $payment = $invoice->payments()->create([
                'type' => 'payment', 'amount' => $amount,
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

    /** Hoàn tiền cho khách: tối đa số đã thu. */
    public function refund(Invoice $invoice, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = $this->lock($invoice);
            $amount = $this->amount($data['amount']);
            if (Decimal::cmp($amount, $invoice->amount_paid) > 0) {
                throw ValidationException::withMessages(['amount' => __('Số tiền hoàn vượt số đã thu (:amount).', ['amount' => money($invoice->amount_paid)])]);
            }
            $refund = $invoice->payments()->create([
                'type' => 'refund', 'amount' => Decimal::mul($amount, -1),
                'payment_date' => $data['payment_date'] ?? today(),
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'],
                'recorded_by' => $user->id,
                'is_demo' => $invoice->is_demo,
            ]);
            $this->refreshInvoice($invoice);
            ActivityLogger::log('payment', $invoice, 'Hoàn tiền '.money($amount).' cho hóa đơn '.$invoice->number.': '.$data['notes']);

            return $refund;
        });
    }

    /** Phiếu giảm trừ: tối đa phần giá trị hóa đơn chưa giảm trừ. */
    public function credit(Invoice $invoice, array $data, User $user): CreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = $this->lock($invoice);
            $amount = $this->amount($data['amount']);
            $left = Decimal::sub($invoice->total, $invoice->credited_amount);
            if (Decimal::cmp($amount, $left) > 0) {
                throw ValidationException::withMessages(['amount' => __('Giá trị giảm trừ vượt phần hóa đơn chưa giảm trừ (:amount).', ['amount' => money($left)])]);
            }
            $note = CreditNote::create(['invoice_id' => $invoice->id, 'issue_date' => $data['issue_date'] ?? today(), 'amount' => $amount,
                'reason' => $data['reason'], 'created_by' => $user->id, 'is_demo' => $invoice->is_demo]);
            $this->refreshInvoice($invoice);
            ActivityLogger::log('payment', $invoice, 'Lập phiếu giảm trừ '.$note->number.' '.money($amount).' cho hóa đơn '.$invoice->number.': '.$data['reason']);

            return $note;
        });
    }

    public function delete(Payment $payment): void
    {
        if ($payment->reconciled_at) {
            throw ValidationException::withMessages(['payment' => __('Khoản đã đối soát không xóa được — hãy bỏ đối soát trước.')]);
        }
        DB::transaction(function () use ($payment) {
            $invoice = $this->lock($payment->invoice);
            $payment->delete();
            $this->refreshInvoice($invoice);
        });
    }

    /** Còn phải thu = tổng − giảm trừ − đã thu (không âm). */
    public static function due(Invoice $invoice): string
    {
        return Decimal::round(Decimal::clamp(Decimal::sub(Decimal::sub($invoice->total, $invoice->credited_amount), $invoice->amount_paid), 0));
    }

    /** Tính lại đã thu, đã giảm trừ và trạng thái thanh toán từ dữ liệu gốc. */
    public function refreshInvoice(Invoice $invoice): void
    {
        $paid = Decimal::round($invoice->payments()->sum('amount'));
        $credited = Decimal::round(CreditNote::where('invoice_id', $invoice->id)->sum('amount'));
        $payable = Decimal::sub($invoice->total, $credited);

        $status = match (true) {
            Decimal::cmp($payable, 0) <= 0 => 'paid',
            Decimal::cmp($paid, 0) <= 0 => 'unpaid',
            Decimal::cmp($paid, $payable) >= 0 => 'paid',
            default => 'partial',
        };

        $invoice->forceFill(['amount_paid' => $paid, 'credited_amount' => $credited, 'payment_status' => $status])->saveQuietly();
    }

    private function lock(Invoice $invoice): Invoice
    {
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        if ($invoice->status !== 'issued') {
            throw ValidationException::withMessages(['amount' => __('Chỉ thao tác thanh toán với hóa đơn đã phát hành.')]);
        }

        return $invoice;
    }

    private function amount(mixed $value): string
    {
        $amount = Decimal::round(is_numeric($value) ? (string) $value : 0);
        if (Decimal::cmp($amount, 0) <= 0) {
            throw ValidationException::withMessages(['amount' => __('Số tiền phải lớn hơn 0.')]);
        }

        return $amount;
    }
}
