<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ExportService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private function filtered(Request $request)
    {
        return SupportTicket::visibleTo($request->user())->with(['customer:id,name', 'assignee:id,name,avatar'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('subject', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $request->status === 'active'
                ? $q->whereIn('status', ['open', 'in_progress', 'waiting']) : $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->assigned_to));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', SupportTicket::class);
        $tickets = $this->applySort($this->filtered($request), $request, ['code', 'subject', 'priority', 'status', 'created_at'])
            ->paginate($this->perPage($request))->withQueryString();
        $counts = SupportTicket::visibleTo($request->user())->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        return view('tickets.index', ['tickets' => $tickets, 'counts' => $counts, 'users' => Lookup::users()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', SupportTicket::class);

        return view('tickets.form', $this->formData($request) + ['ticket' => new SupportTicket([
            'customer_id' => $request->integer('customer_id') ?: null, 'priority' => 'medium', 'status' => 'open',
        ])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SupportTicket::class);
        $data = $this->validated($request);
        $ticket = SupportTicket::create($data + ['created_by' => $request->user()->id]);

        // Thông báo ticket mới cho người được giao, hoặc cho nhân viên CSKH nếu chưa giao
        $recipients = $ticket->assignee ? collect([$ticket->assignee])
            : User::inOrganization()->role(['Support Agent', 'Customer Support Manager'])->active()->get();
        foreach ($recipients as $u) {
            if ($u->id !== $request->user()->id) {
                CrmNotification::send($u, 'ticket_new', 'Ticket mới '.$ticket->code.': '.$ticket->subject, route('tickets.show', $ticket), 'bi-headset');
            }
        }

        return redirect()->route('tickets.show', $ticket)->with('success', __('Đã tạo ticket :code.', ['code' => $ticket->code]));
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorize('view', $ticket);
        $ticket->load(['customer', 'contact', 'assignee', 'creator', 'replies.user:id,name,avatar']);

        return view('tickets.show', ['ticket' => $ticket, 'users' => Lookup::users()]);
    }

    public function edit(Request $request, SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);

        return view('tickets.form', $this->formData($request) + ['ticket' => $ticket]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);
        $previous = $ticket->assigned_to;
        $ticket->update($this->validated($request, $ticket));
        if ($ticket->assigned_to && $ticket->assigned_to !== $previous && $ticket->assigned_to !== $request->user()->id) {
            CrmNotification::send($ticket->assignee, 'ticket_new', 'Bạn được giao ticket '.$ticket->code.': '.$ticket->subject, route('tickets.show', $ticket), 'bi-headset');
        }

        return redirect()->route('tickets.show', $ticket)->with('success', __('Đã cập nhật ticket.'));
    }

    public function destroy(SupportTicket $ticket)
    {
        $this->authorize('delete', $ticket);
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', __('Đã xóa ticket.').' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    /** Phản hồi khách hàng hoặc ghi chú nội bộ; có thể đổi trạng thái cùng lúc. */
    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
        ], [], ['body' => __('Nội dung')]);

        $ticket->replies()->create(['body' => $data['body'], 'is_internal' => $request->boolean('is_internal'), 'user_id' => $request->user()->id]);
        if (! empty($data['status']) && $data['status'] !== $ticket->status) {
            $ticket->update($this->statusTimestamps($data['status'], $ticket) + ['status' => $data['status']]);
        } elseif ($ticket->status === 'open' && ! $request->boolean('is_internal')) {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', $request->boolean('is_internal') ? __('Đã thêm ghi chú nội bộ.') : __('Đã gửi phản hồi.'));
    }

    public function export(Request $request, ExportService $exporter)
    {
        $this->authorize('export', SupportTicket::class);
        $rows = $this->filtered($request)->orderBy('id')->limit(20000)->get()->map(fn (SupportTicket $t) => [
            $t->code, $t->customer?->name, $t->subject, label_of(SupportTicket::PRIORITIES, $t->priority), label_of(SupportTicket::STATUSES, $t->status),
            $t->assignee?->name, vn_date($t->created_at, true), vn_date($t->resolved_at, true),
        ]);

        return $exporter->download('ticket_ho_tro', [__('Mã'), __('Khách hàng'), __('Chủ đề'), __('Ưu tiên'), __('Trạng thái'), __('Phụ trách'), __('Tạo lúc'), __('Giải quyết lúc')], $rows, $request->input('format', 'xlsx'));
    }

    private function statusTimestamps(string $status, ?SupportTicket $ticket): array
    {
        return [
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? ($ticket?->resolved_at ?? now()) : null,
            'closed_at' => $status === 'closed' ? ($ticket?->closed_at ?? now()) : null,
        ];
    }

    private function validated(Request $request, ?SupportTicket $existing = null): array
    {
        $data = $request->validate([
            'customer_id' => ['required', \App\Support\Tenancy\TenantRule::exists('customers')],
            'contact_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('contacts')],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
        ], [], ['subject' => __('Chủ đề'), 'description' => __('Mô tả')]);

        // Ticket gắn với khách hàng mà người tạo được phép xem (CSKH có quyền xem tất cả khách hàng)
        abort_unless(Customer::visibleTo($request->user())->whereKey($data['customer_id'])->exists(), 403, __('Bạn không có quyền với khách hàng này.'));
        if (! empty($data['contact_id'])) {
            abort_unless(Contact::whereKey($data['contact_id'])->where('customer_id', $data['customer_id'])->exists(), 422, __('Người liên hệ không thuộc khách hàng đã chọn.'));
        }
        if (! $request->user()->can('tickets.assign')) {
            $data['assigned_to'] = $existing ? $existing->assigned_to : null;
        }

        return $data + $this->statusTimestamps($data['status'], $existing);
    }

    private function formData(Request $request): array
    {
        return [
            'customers' => Lookup::customers($request->user()),
            'contacts' => Contact::visibleTo($request->user())->orderBy('full_name')->get(['id', 'full_name', 'customer_id']),
            'users' => Lookup::users(),
        ];
    }
}
