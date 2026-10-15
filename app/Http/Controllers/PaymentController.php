<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function store(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->payments->record($invoice, $data, $request->user());
        ActivityLogger::log('payment', $invoice, 'Ghi nhận thanh toán '.money($payment->amount).' cho hóa đơn '.$invoice->number);

        return back()->with('success', 'Đã ghi nhận thanh toán '.money($payment->amount).'.');
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('update', $payment->invoice);
        abort_unless(auth()->user()->can('invoices.delete'), 403);
        $this->payments->delete($payment);

        return back()->with('success', 'Đã xóa khoản thanh toán.');
    }
}
