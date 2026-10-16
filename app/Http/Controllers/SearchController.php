<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

/** Tìm kiếm toàn cục (AJAX) — chỉ trả về dữ liệu người dùng được phép xem. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $user = $request->user();
        $term = $this->like($request->q);
        $groups = [];

        $add = function (string $label, string $icon, $items) use (&$groups) {
            if ($items->isNotEmpty()) {
                $groups[] = ['label' => $label, 'icon' => $icon, 'items' => $items->values()];
            }
        };

        if ($user->can('customers.view')) {
            $add(__('Khách hàng'), 'bi-people', Customer::visibleTo($user)
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term))
                ->limit(5)->get()->map(fn ($c) => ['title' => $c->name, 'subtitle' => $c->code.' · '.($c->phone ?? $c->email), 'url' => route('customers.show', $c)]));
        }
        if ($user->can('leads.view')) {
            $add('Leads', 'bi-funnel', Lead::visibleTo($user)
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('company', 'like', $term)->orWhere('email', 'like', $term))
                ->limit(5)->get()->map(fn ($l) => ['title' => $l->name, 'subtitle' => $l->code.' · '.label_of(Lead::statuses(), $l->status), 'url' => route('leads.show', $l)]));
        }
        if ($user->can('opportunities.view')) {
            $add(__('Cơ hội'), 'bi-kanban', Opportunity::visibleTo($user)
                ->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('code', 'like', $term))
                ->limit(5)->get()->map(fn ($o) => ['title' => $o->title, 'subtitle' => $o->code.' · '.money($o->value), 'url' => route('opportunities.show', $o)]));
        }
        if ($user->can('contacts.view')) {
            $add(__('Người liên hệ'), 'bi-person-lines-fill', Contact::visibleTo($user)
                ->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term))
                ->limit(5)->get()->map(fn ($c) => ['title' => $c->full_name, 'subtitle' => $c->company ?? $c->email, 'url' => route('contacts.show', $c)]));
        }
        if ($user->can('invoices.view')) {
            $add(__('Hóa đơn'), 'bi-receipt', Invoice::visibleTo($user)->where('number', 'like', $term)
                ->limit(5)->get()->map(fn ($i) => ['title' => $i->number, 'subtitle' => money($i->total), 'url' => route('invoices.show', $i)]));
        }
        if ($user->can('tickets.view')) {
            $add(__('Ticket hỗ trợ'), 'bi-headset', SupportTicket::visibleTo($user)
                ->where(fn ($q) => $q->where('subject', 'like', $term)->orWhere('code', 'like', $term))
                ->limit(5)->get()->map(fn ($t) => ['title' => $t->subject, 'subtitle' => $t->code.' · '.label_of(SupportTicket::STATUSES, $t->status), 'url' => route('tickets.show', $t)]));
        }

        return response()->json(['groups' => $groups]);
    }
}
