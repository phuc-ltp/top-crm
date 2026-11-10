<?php

namespace App\Services\Payments;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Support\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nhận giao dịch từ webhook SePay (https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook):
 *  - chống trùng theo `id` của SePay (không đổi qua các lần gửi lại);
 *  - chỉ xử lý tiền vào; tìm DUY NHẤT một số hóa đơn (HDxxxxx) trong `code` / `content`
 *    (nội dung mã VietQR của CRM chính là số hóa đơn);
 *  - số tiền ≤ số còn phải thu → tự ghi nhận thanh toán; còn lại để "chưa khớp" cho kế toán xử lý tay.
 * Phải gọi bên trong CurrentOrganization::run() của tổ chức sở hữu webhook.
 */
class SepayWebhook
{
    public const PROVIDER = 'sepay';

    /** Tổ chức sở hữu token webhook (token 40 ký tự, ngẫu nhiên, đổi được). */
    public static function organizationFor(string $token): ?int
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }
        $id = OrganizationSetting::where('key', 'sepay_webhook_token')->where('value', $token)->value('organization_id');

        return $id && Organization::whereKey($id)->where('status', 'active')->exists() ? (int) $id : null;
    }

    /** Các số hóa đơn khác nhau xuất hiện trong chuỗi (chấp nhận "HD00010", "hd 00010", "HD-00010"). */
    public static function invoiceNumbers(string $text): array
    {
        preg_match_all('/(?<![A-Z0-9])HD[\s.\-_]?(\d{5})(?!\d)/', mb_strtoupper($text), $m);

        return array_values(array_unique(array_map(fn ($d) => 'HD'.$d, $m[1])));
    }

    /** @param array{id:int, transferType:string, transferAmount:int|float|string, content?:?string, code?:?string} $data */
    public function handle(array $data): BankTransaction
    {
        return DB::transaction(function () use ($data) {
            try {
                $tx = BankTransaction::create([
                    'provider' => self::PROVIDER,
                    'provider_id' => (int) $data['id'],
                    'gateway' => Str::limit((string) ($data['gateway'] ?? ''), 100, '') ?: null,
                    'account_number' => Str::limit((string) ($data['accountNumber'] ?? ''), 50, '') ?: null,
                    'transfer_type' => $data['transferType'],
                    'amount' => Decimal::round((string) $data['transferAmount'], PaymentService::scale()),
                    'content' => Str::limit((string) ($data['content'] ?? ''), 2000, '') ?: null,
                    'code' => Str::limit((string) ($data['code'] ?? ''), 100, '') ?: null,
                    'reference_code' => Str::limit((string) ($data['referenceCode'] ?? ''), 100, '') ?: null,
                    'transaction_at' => $this->date($data['transactionDate'] ?? null),
                    'status' => 'unmatched',
                ]);
            } catch (UniqueConstraintViolationException) {
                // SePay gửi lại cùng giao dịch → trả về bản đã có, không ghi nhận lần nữa
                return BankTransaction::where('provider', self::PROVIDER)->where('provider_id', (int) $data['id'])->firstOrFail();
            }

            if ($tx->transfer_type !== 'in') {
                $tx->update(['status' => 'ignored', 'note' => 'Giao dịch tiền ra']);

                return $tx;
            }
            $this->match($tx);

            return $tx;
        });
    }

    private function match(BankTransaction $tx): void
    {
        $numbers = self::invoiceNumbers(($tx->code ?? '').' '.($tx->content ?? ''));
        if (count($numbers) !== 1) {
            $this->unmatched($tx, null, $numbers === [] ? 'Nội dung không có số hóa đơn (HDxxxxx)' : 'Nội dung có nhiều số hóa đơn: '.implode(', ', $numbers));

            return;
        }
        $invoice = Invoice::where('number', $numbers[0])->first();
        if (! $invoice) {
            $this->unmatched($tx, null, 'Không tìm thấy hóa đơn '.$numbers[0]);

            return;
        }
        $due = PaymentService::due($invoice);
        $problem = match (true) {
            $invoice->status !== 'issued' => 'Hóa đơn '.$invoice->number.' chưa phát hành hoặc đã hủy',
            Decimal::cmp($due, 0) <= 0 => 'Hóa đơn '.$invoice->number.' đã thu đủ',
            Decimal::cmp($tx->amount, $due) > 0 => 'Số tiền lớn hơn số còn phải thu của '.$invoice->number.' ('.money($due).')',
            default => null,
        };
        $owner = $this->owner($invoice->organization_id);
        if (! $problem && ! $owner) {
            $problem = 'Tổ chức chưa có chủ sở hữu để ghi nhận';
        }
        if ($problem) {
            $this->unmatched($tx, $invoice, $problem);

            return;
        }

        try {
            $payment = app(PaymentService::class)->record($invoice, [
                'amount' => $tx->amount,
                'payment_date' => ($tx->transaction_at ?? now())->toDateString(),
                'method' => 'bank_transfer',
                'reference' => $tx->reference_code ?: 'SePay #'.$tx->provider_id,
                'notes' => Str::limit('Tự động qua SePay'.($tx->gateway ? ' ('.$tx->gateway.')' : '').': '.$tx->content, 500, ''),
            ], $owner);
        } catch (ValidationException $e) {
            $this->unmatched($tx, $invoice, Str::limit(collect($e->errors())->flatten()->first() ?? 'Không ghi nhận được', 255, ''));

            return;
        }
        $tx->update(['status' => 'matched', 'invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'note' => null]);
        ActivityLogger::log('payment', $invoice, 'SePay: tự ghi nhận '.money($tx->amount).' cho hóa đơn '.$invoice->number);
        CrmNotification::send($owner, 'payment_received',
            __('SePay: nhận :amount cho hóa đơn :number (còn phải thu :due).', ['amount' => money($tx->amount), 'number' => $invoice->number, 'due' => money(PaymentService::due($invoice->fresh()))]),
            route('invoices.show', $invoice), 'bi-bank');
    }

    private function unmatched(BankTransaction $tx, ?Invoice $invoice, string $note): void
    {
        $tx->update(['status' => 'unmatched', 'invoice_id' => $invoice?->id, 'note' => Str::limit($note, 255, '')]);
        $owner = $this->owner($tx->organization_id);
        CrmNotification::send($owner, 'payment_received',
            __('SePay: có tiền vào :amount chưa khớp hóa đơn — :note.', ['amount' => money($tx->amount), 'note' => $note]),
            route('settings.sepay'), 'bi-exclamation-diamond');
    }

    /** Người đứng tên ghi nhận / nhận thông báo: chủ sở hữu tổ chức, nếu chưa gán thì người có vai trò Chủ sở hữu. */
    private function owner(int $organizationId): ?\App\Models\User
    {
        $owner = Organization::find($organizationId)?->owner;
        if ($owner && $owner->isActive()) {
            return $owner;
        }

        return \App\Models\User::inOrganization($organizationId)->role(\App\Support\PermissionCatalog::OWNER)->where('status', 'active')->orderBy('id')->first();
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)
                ? Carbon::createFromFormat('Y-m-d H:i:s', $value, 'Asia/Ho_Chi_Minh')->setTimezone(config('app.timezone'))
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
