<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Thanh toán / hoàn tiền / phiếu giảm trừ của hóa đơn và đối soát thanh toán.
 * Đối soát: người xem được mọi hóa đơn và được sửa hóa đơn (kế toán, quản lý tài chính).
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    private function moneyRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function store(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate($this->moneyRules() + ['notes' => ['nullable', 'string', 'max:500']]);

        $payment = $this->payments->record($invoice, $data, $request->user());
        ActivityLogger::log('payment', $invoice, 'Ghi nhận thanh toán '.money($payment->amount).' cho hóa đơn '.$invoice->number);

        return back()->with('success', __('Đã ghi nhận thanh toán :amount.', ['amount' => money($payment->amount)]));
    }

    public function refund(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate($this->moneyRules() + ['notes' => ['required', 'string', 'max:500']], [], ['notes' => __('Lý do hoàn tiền')]);
        $refund = $this->payments->refund($invoice, $data, $request->user());

        return back()->with('success', __('Đã ghi nhận hoàn tiền :amount.', ['amount' => money(abs((float) $refund->amount))]));
    }

    public function credit(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => __('Lý do giảm trừ')]);
        $note = $this->payments->credit($invoice, $data, $request->user());

        return back()->with('success', __('Đã lập phiếu giảm trừ :number (:amount).', ['number' => $note->number, 'amount' => money($note->amount)]));
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('update', $payment->invoice);
        abort_unless(auth()->user()->can('invoices.delete'), 403);
        $this->payments->delete($payment);

        return back()->with('success', __('Đã xóa khoản thanh toán.'));
    }

    // ---------------------------------------------------------------- đối soát

    private function authorizeReconcile(Request $request): void
    {
        abort_unless($request->user()->can('invoices.view_all') && $request->user()->can('invoices.update'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeReconcile($request);
        $data = $request->validate([
            'state' => ['nullable', Rule::in(['open', 'done'])],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = Payment::with(['invoice:id,number,customer_id', 'invoice.customer:id,name', 'recorder:id,name', 'reconciler:id,name'])
            ->when(($data['state'] ?? 'open') === 'open', fn ($q) => $q->whereNull('reconciled_at'), fn ($q) => $q->whereNotNull('reconciled_at'))
            ->when($data['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->when($data['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('reference', 'like', $this->like($t))
                ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', $this->like($t)))));
        $total = (float) (clone $query)->sum('amount');
        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate($this->perPage($request))->withQueryString();

        return view('payments.index', compact('payments', 'total'));
    }

    /** Đánh dấu đã đối soát (nhiều khoản) / bỏ đối soát (một khoản). */
    public function reconcile(Request $request)
    {
        $this->authorizeReconcile($request);
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'undo' => ['nullable', 'boolean']]);
        $undo = $request->boolean('undo');
        $count = 0;
        foreach (Payment::whereIn('id', $data['ids'])->get() as $p) {
            $p->forceFill($undo ? ['reconciled_at' => null, 'reconciled_by' => null] : ['reconciled_at' => now(), 'reconciled_by' => $request->user()->id])->save();
            ActivityLogger::log('updated', $p->invoice, ($undo ? 'Bỏ đối soát ' : 'Đối soát ').'khoản '.money($p->amount).' ('.($p->reference ?: vn_date($p->payment_date)).') của hóa đơn '.$p->invoice?->number);
            $count++;
        }

        return back()->with('success', $undo ? __('Đã bỏ đối soát :count khoản.', ['count' => $count]) : __('Đã đối soát :count khoản.', ['count' => $count]));
    }
}
