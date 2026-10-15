<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Services\ExportService;
use App\Support\Lookup;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    private function rules(): array
    {
        return [
            'customer_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('customers')],
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function filtered(Request $request)
    {
        return Contact::visibleTo($request->user())->with('customer:id,code,name')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('full_name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)->orWhere('company', 'like', $term));
            })
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Contact::class);
        $contacts = $this->applySort($this->filtered($request), $request, ['full_name', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();

        return view('contacts.index', compact('contacts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Contact::class);
        $contact = new Contact(['customer_id' => $request->integer('customer_id') ?: null]);

        return view('contacts.form', ['contact' => $contact, 'customers' => Lookup::customers($request->user())]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Contact::class);
        $data = $request->validate($this->rules(), [], ['full_name' => __('Họ tên')]);
        $this->ensureCustomerVisible($request, $data['customer_id'] ?? null);

        $contact = Contact::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('contacts.show', $contact)->with('success', __('Đã thêm người liên hệ.'));
    }

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);
        $contact->load(['customer', 'creator', 'interactions.user:id,name']);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        return view('contacts.form', ['contact' => $contact, 'customers' => Lookup::customers($request->user())]);
    }

    public function update(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);
        $data = $request->validate($this->rules(), [], ['full_name' => __('Họ tên')]);
        $this->ensureCustomerVisible($request, $data['customer_id'] ?? null);
        $contact->update($data);

        return redirect()->route('contacts.show', $contact)->with('success', __('Đã cập nhật người liên hệ.'));
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('delete', $contact);
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', __('Đã xóa người liên hệ.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', Contact::class);
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn ($c) => [
            $c->full_name, $c->position, $c->email, $c->phone, $c->company, $c->customer?->name, vn_date($c->created_at),
        ]);

        return $exporter->download('nguoi_lien_he', [__('Họ tên'), __('Chức vụ'), 'Email', __('Điện thoại'), __('Công ty'), __('Khách hàng'), __('Ngày tạo')], $rows, $request->input('format', 'xlsx'));
    }

    private function ensureCustomerVisible(Request $request, $customerId): void
    {
        if ($customerId) {
            abort_unless(Customer::visibleTo($request->user())->whereKey($customerId)->exists(), 403, __('Bạn không có quyền với khách hàng này.'));
        }
    }
}
