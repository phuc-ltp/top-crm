<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SearchHistory;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tìm kiếm toàn cục. Mọi loại dữ liệu đều đi qua:
 *  1) quyền xem module, 2) phạm vi visibleTo ("chỉ của mình" khi không có quyền xem tất cả),
 *  3) global scope tổ chức của model → không bao giờ trả bản ghi người dùng không được xem.
 * Tìm bằng LIKE (đúng với tiếng Việt có dấu, từ 2 ký tự); số điện thoại được chuẩn hóa (+84 / dấu cách).
 */
class GlobalSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public const RECENT_LIMIT = 10;

    /** @return array<string, array{label: string, icon: string, permission: string}> */
    public static function types(): array
    {
        return [
            'customers' => ['label' => 'Khách hàng', 'icon' => 'bi-people', 'permission' => 'customers.view'],
            'contacts' => ['label' => 'Người liên hệ', 'icon' => 'bi-person-lines-fill', 'permission' => 'contacts.view'],
            'leads' => ['label' => 'Leads', 'icon' => 'bi-funnel', 'permission' => 'leads.view'],
            'opportunities' => ['label' => 'Cơ hội', 'icon' => 'bi-kanban', 'permission' => 'opportunities.view'],
            'quotations' => ['label' => 'Báo giá', 'icon' => 'bi-file-earmark-text', 'permission' => 'quotations.view'],
            'orders' => ['label' => 'Đơn hàng', 'icon' => 'bi-bag-check', 'permission' => 'orders.view'],
            'invoices' => ['label' => 'Hóa đơn', 'icon' => 'bi-receipt', 'permission' => 'invoices.view'],
            'products' => ['label' => 'Sản phẩm', 'icon' => 'bi-box-seam', 'permission' => 'products.view'],
            'tickets' => ['label' => 'Ticket hỗ trợ', 'icon' => 'bi-headset', 'permission' => 'tickets.view'],
            'tasks' => ['label' => 'Công việc', 'icon' => 'bi-check2-square', 'permission' => 'tasks.view'],
            'employees' => ['label' => 'Nhân viên', 'icon' => 'bi-person-badge', 'permission' => 'users.view'],
        ];
    }

    /** Các loại người dùng được tìm. */
    public function allowedTypes(User $user): array
    {
        return array_keys(array_filter(self::types(), fn ($t) => $user->can($t['permission'])));
    }

    public static function normalize(?string $q): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $q)), 0, self::MAX_LENGTH);
    }

    private function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * So khớp số điện thoại bỏ qua dấu cách / chấm / gạch / ngoặc đã lưu, và chấp nhận cả dạng +84 lẫn 0.
     * $digits đã chuẩn hóa (chỉ chữ số, đầu 0 thay cho 84).
     */
    private function phoneMatch($w, string $digits): void
    {
        $stripped = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '.', ''), '-', ''), '(', ''), ')', ''), '+', '')";
        $w->orWhereRaw("$stripped LIKE ?", ['%'.$digits.'%']);
        if (str_starts_with($digits, '0') && strlen($digits) >= 5) {
            $w->orWhereRaw("$stripped LIKE ?", ['%84'.substr($digits, 1).'%']);
        }
    }

    /** Truy vấn đã lọc quyền + từ khóa cho một loại. */
    public function query(string $type, string $q, User $user): Builder
    {
        $term = $this->like($q);
        $phone = CustomerService::normalizePhone($q);
        // Chỉ coi là tìm số điện thoại khi từ khóa chủ yếu là chữ số (≥ 4 số)
        $phoneTerm = $phone && strlen($phone) >= 4 && preg_match('/^[\d\s\.\-\+\(\)]+$/', $q) ? $phone : null;
        $text = fn (array $cols) => function ($w) use ($cols, $term) {
            foreach ($cols as $c) {
                $w->orWhere($c, 'like', $term);
            }
        };

        return match ($type) {
            'customers' => Customer::visibleTo($user)->where(function ($w) use ($text, $phoneTerm) {
                $w->where($text(['name', 'code', 'email', 'phone', 'company']));
                if ($phoneTerm) {
                    $this->phoneMatch($w, $phoneTerm);
                }
            })->orderBy('name'),
            'contacts' => Contact::visibleTo($user)->with('customer:id,name')->where(function ($w) use ($text, $phoneTerm) {
                $w->where($text(['full_name', 'email', 'phone', 'company']));
                if ($phoneTerm) {
                    $this->phoneMatch($w, $phoneTerm);
                }
            })->orderBy('full_name'),
            'leads' => Lead::visibleTo($user)->where(function ($w) use ($text, $phoneTerm) {
                $w->where($text(['name', 'code', 'company', 'email', 'phone']));
                if ($phoneTerm) {
                    $this->phoneMatch($w, $phoneTerm);
                }
            })->latest(),
            'opportunities' => Opportunity::visibleTo($user)->with(['customer:id,name', 'stage:id,name'])
                ->where(fn ($w) => $w->where($text(['title', 'code']))->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)))->latest(),
            'quotations' => Quotation::visibleTo($user)->with('customer:id,name')
                ->where(fn ($w) => $w->where('number', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)))->latest(),
            'orders' => SalesOrder::visibleTo($user)->with('customer:id,name')
                ->where(fn ($w) => $w->where('number', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)))->latest(),
            'invoices' => Invoice::visibleTo($user)->with('customer:id,name')
                ->where(fn ($w) => $w->where('number', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)))->latest(),
            'products' => Product::query()->where($text(['name', 'sku']))->orderBy('name'),
            'tickets' => SupportTicket::visibleTo($user)->with('customer:id,name')->where($text(['subject', 'code']))->latest(),
            'tasks' => Task::visibleTo($user)->with('assignee:id,name')->where($text(['title']))->orderByRaw('due_date IS NULL, due_date'),
            'employees' => User::inOrganization()->where($text(['name', 'email', 'phone', 'position']))->orderBy('name'),
            default => throw new \InvalidArgumentException('Unknown search type'),
        };
    }

    /** Một dòng kết quả để hiển thị: tiêu đề, mô tả, đường dẫn. */
    public function present(string $type, $m): array
    {
        return match ($type) {
            'customers' => ['title' => $m->name, 'subtitle' => trim($m->code.' · '.($m->phone ?? $m->email ?? ''), ' ·'), 'url' => route('customers.show', $m)],
            'contacts' => ['title' => $m->full_name, 'subtitle' => $m->customer?->name ?? $m->company ?? $m->email, 'url' => route('contacts.show', $m)],
            'leads' => ['title' => $m->name, 'subtitle' => $m->code.' · '.label_of(Lead::statuses(), $m->status), 'url' => route('leads.show', $m)],
            'opportunities' => ['title' => $m->title, 'subtitle' => $m->code.' · '.($m->customer?->name ?? '').' · '.money($m->value), 'url' => route('opportunities.show', $m)],
            'quotations' => ['title' => $m->number, 'subtitle' => ($m->customer?->name ?? '').' · '.money($m->total), 'url' => route('quotations.show', $m)],
            'orders' => ['title' => $m->number, 'subtitle' => ($m->customer?->name ?? '').' · '.money($m->total), 'url' => route('orders.show', $m)],
            'invoices' => ['title' => $m->number, 'subtitle' => ($m->customer?->name ?? '').' · '.money($m->total), 'url' => route('invoices.show', $m)],
            'products' => ['title' => $m->name, 'subtitle' => $m->sku.' · '.money($m->price), 'url' => route('products.show', $m)],
            'tickets' => ['title' => $m->subject, 'subtitle' => $m->code.' · '.label_of(SupportTicket::STATUSES, $m->status), 'url' => route('tickets.show', $m)],
            'tasks' => ['title' => $m->title, 'subtitle' => vn_date($m->due_date, true).($m->assignee ? ' · '.$m->assignee->name : ''), 'url' => route('tasks.show', $m)],
            'employees' => ['title' => $m->name, 'subtitle' => trim(($m->position ? $m->position.' · ' : '').$m->email), 'url' => route('users.show', $m)],
        };
    }

    /** Gợi ý nhanh (ô tìm kiếm): tối đa $perType kết quả mỗi loại. */
    public function suggest(string $q, User $user, int $perType = 5): array
    {
        $groups = [];
        foreach ($this->allowedTypes($user) as $type) {
            $items = $this->query($type, $q, $user)->limit($perType)->get();
            if ($items->isNotEmpty()) {
                $def = self::types()[$type];
                $groups[] = ['type' => $type, 'label' => __($def['label']), 'icon' => $def['icon'],
                    'items' => $items->map(fn ($m) => $this->present($type, $m))->values()];
            }
        }

        return $groups;
    }

    /** Số kết quả theo từng loại (trang kết quả đầy đủ). */
    public function counts(string $q, User $user): array
    {
        $counts = [];
        foreach ($this->allowedTypes($user) as $type) {
            $counts[$type] = $this->query($type, $q, $user)->count();
        }

        return $counts;
    }

    // ---------------- Tìm gần đây ----------------

    public function remember(string $q, User $user, int $organizationId): void
    {
        if (mb_strlen($q) < self::MIN_LENGTH) {
            return;
        }
        SearchHistory::updateOrCreate(['organization_id' => $organizationId, 'user_id' => $user->id, 'query' => $q], ['updated_at' => now()])->touch();
        $keep = SearchHistory::where('organization_id', $organizationId)->where('user_id', $user->id)->latest('updated_at')->limit(self::RECENT_LIMIT)->pluck('id');
        SearchHistory::where('organization_id', $organizationId)->where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
    }

    public function recent(User $user, int $organizationId): Collection
    {
        return SearchHistory::where('organization_id', $organizationId)->where('user_id', $user->id)
            ->latest('updated_at')->limit(self::RECENT_LIMIT)->pluck('query');
    }
}
