<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dòng thời gian thống nhất cho khách hàng / người liên hệ / lead / cơ hội.
 * Mỗi nguồn dữ liệu đều đi qua quyền module + phạm vi visibleTo của người xem → không lộ bản ghi không được xem.
 * Hoạt động "đã lên lịch" không nằm ở đây (hiển thị riêng ở mục Sắp tới).
 */
class TimelineService
{
    public const LIMIT = 60;

    /** @return Collection<int, array{at:\Illuminate\Support\Carbon, kind:string, icon:string, tone:string, title:string, meta:?string, body:?string, url:?string, activity:?Interaction}> */
    public function for($subject, User $viewer): Collection
    {
        $items = collect();
        $push = function ($at, string $kind, string $icon, string $tone, string $title, ?string $meta = null, ?string $body = null, ?string $url = null, ?Interaction $activity = null) use (&$items) {
            if ($at) {
                $items->push(compact('at', 'kind', 'icon', 'tone', 'title', 'meta', 'body', 'url', 'activity'));
            }
        };

        // 1. Hoạt động đã hoàn thành
        $subject->interactions()->where('status', 'done')->with(['user:id,name', 'assignee:id,name'])->latest('occurred_at')->limit(self::LIMIT)->get()
            ->each(fn (Interaction $a) => $push($a->moment(), 'activity', Interaction::TYPES[$a->type][1] ?? 'bi-dot', 'orange',
                label_of(Interaction::TYPES, $a->type).($a->subject ? ' · '.$a->subject : ''),
                $a->user?->name, $a->content, null, $a));

        // 2. Thay đổi quan trọng ghi trong nhật ký (tạo, cập nhật, phân công, chuyển đổi...)
        if ($viewer->can('activity_logs.view') || $viewer->can('update', $subject)) {
            ActivityLog::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->with('user:id,name')->latest('created_at')->limit(20)->get()
                ->each(fn ($log) => $push($log->created_at, 'log', 'bi-clock-history', 'gray', $log->description, $log->user?->name));
        }

        // 3. Dữ liệu liên quan theo loại đối tượng
        match (true) {
            $subject instanceof Customer => $this->customerRecords($subject, $viewer, $push),
            $subject instanceof Opportunity => $this->opportunityRecords($subject, $viewer, $push),
            $subject instanceof Lead => $this->leadRecords($subject, $viewer, $push),
            default => null,
        };

        return $items->sortByDesc(fn ($i) => $i['at']->getTimestamp())->take(self::LIMIT)->values();
    }

    private function customerRecords(Customer $c, User $viewer, callable $push): void
    {
        if ($viewer->can('opportunities.view')) {
            Opportunity::visibleTo($viewer)->where('customer_id', $c->id)->with('stage:id,name')->latest()->limit(15)->get()
                ->each(fn ($o) => $push($o->created_at, 'opportunity', 'bi-kanban', 'purple', __('Cơ hội :title', ['title' => $o->title]),
                    $o->stage?->name.' · '.money($o->value), null, route('opportunities.show', $o)));
        }
        if ($viewer->can('quotations.view')) {
            Quotation::visibleTo($viewer)->where('customer_id', $c->id)->latest()->limit(15)->get()
                ->each(fn ($q) => $push($q->created_at, 'quotation', 'bi-file-earmark-text', 'blue', __('Báo giá :code', ['code' => $q->number]),
                    label_of(Quotation::STATUSES, $q->status).' · '.money($q->total), null, route('quotations.show', $q)));
        }
        if ($viewer->can('orders.view')) {
            SalesOrder::visibleTo($viewer)->where('customer_id', $c->id)->latest()->limit(15)->get()
                ->each(fn ($o) => $push($o->created_at, 'order', 'bi-bag-check', 'blue', __('Đơn hàng :code', ['code' => $o->number]),
                    label_of(SalesOrder::STATUSES, $o->status).' · '.money($o->total), null, route('orders.show', $o)));
        }
        if ($viewer->can('invoices.view')) {
            $invoices = Invoice::visibleTo($viewer)->where('customer_id', $c->id)->latest()->limit(15)->get();
            $invoices->each(fn ($i) => $push($i->issue_date ? $i->issue_date->copy()->setTimeFrom($i->created_at) : $i->created_at, 'invoice', 'bi-receipt', 'green',
                __('Hóa đơn :code', ['code' => $i->number]), label_of(Invoice::STATUSES, $i->status).' · '.money($i->total), null, route('invoices.show', $i)));
            Payment::whereIn('invoice_id', $invoices->pluck('id'))->latest('payment_date')->limit(15)->get()
                ->each(fn ($p) => $push($p->payment_date ? $p->payment_date->copy()->setTimeFrom($p->created_at) : $p->created_at, 'payment', 'bi-cash-coin', 'green',
                    __('Thanh toán :amount', ['amount' => money($p->amount)]), $p->reference, null, route('invoices.show', $p->invoice_id)));
        }
        if ($viewer->can('tickets.view')) {
            SupportTicket::visibleTo($viewer)->where('customer_id', $c->id)->latest()->limit(15)->get()
                ->each(fn ($t) => $push($t->created_at, 'ticket', 'bi-headset', 'red', __('Ticket :code: :subject', ['code' => $t->code, 'subject' => $t->subject]),
                    label_of(SupportTicket::STATUSES, $t->status), null, route('tickets.show', $t)));
        }
    }

    private function opportunityRecords(Opportunity $o, User $viewer, callable $push): void
    {
        $o->stageHistories()->with(['fromStage:id,name', 'toStage:id,name', 'user:id,name'])->latest('created_at')->limit(20)->get()
            ->each(fn ($h) => $push($h->created_at, 'stage', 'bi-arrow-right-circle', 'purple',
                __('Chuyển giai đoạn: :from → :to', ['from' => $h->fromStage?->name ?? '—', 'to' => $h->toStage?->name ?? '—']), $h->user?->name));
        if ($viewer->can('quotations.view')) {
            Quotation::visibleTo($viewer)->where('opportunity_id', $o->id)->latest()->limit(15)->get()
                ->each(fn ($q) => $push($q->created_at, 'quotation', 'bi-file-earmark-text', 'blue', __('Báo giá :code', ['code' => $q->number]),
                    label_of(Quotation::STATUSES, $q->status).' · '.money($q->total), null, route('quotations.show', $q)));
        }
    }

    private function leadRecords(Lead $l, User $viewer, callable $push): void
    {
        if ($l->converted_at) {
            $url = $l->converted_customer_id && $viewer->can('customers.view') && Customer::visibleTo($viewer)->whereKey($l->converted_customer_id)->exists()
                ? route('customers.show', $l->converted_customer_id) : null;
            $push($l->converted_at, 'conversion', 'bi-stars', 'green', __('Đã chuyển đổi thành khách hàng'), null, null, $url);
        }
    }
}
