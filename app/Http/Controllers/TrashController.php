<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Thùng rác: bản ghi đã xóa (xóa mềm) của các module, có thể khôi phục.
 *  - Chỉ hiện loại người dùng có quyền XÓA (cùng điều kiện với nút xóa), chỉ bản ghi trong phạm vi được xem
 *    (visibleTo) và trong tổ chức đang làm việc (global scope của model vẫn áp dụng với onlyTrashed).
 *  - Không có xóa vĩnh viễn: dữ liệu đã xóa luôn còn trong CSDL để khôi phục / đối chiếu.
 */
class TrashController extends Controller
{
    /** @return array<string, array{class: class-string, label: string, icon: string, module: string, show: string}> */
    public static function types(): array
    {
        return [
            'customers' => ['class' => Customer::class, 'label' => 'Khách hàng', 'icon' => 'bi-people', 'module' => 'customers', 'show' => 'customers.show'],
            'contacts' => ['class' => Contact::class, 'label' => 'Người liên hệ', 'icon' => 'bi-person-lines-fill', 'module' => 'contacts', 'show' => 'contacts.show'],
            'leads' => ['class' => Lead::class, 'label' => 'Leads', 'icon' => 'bi-funnel', 'module' => 'leads', 'show' => 'leads.show'],
            'opportunities' => ['class' => Opportunity::class, 'label' => 'Cơ hội', 'icon' => 'bi-kanban', 'module' => 'opportunities', 'show' => 'opportunities.show'],
            'quotations' => ['class' => Quotation::class, 'label' => 'Báo giá', 'icon' => 'bi-file-earmark-text', 'module' => 'quotations', 'show' => 'quotations.show'],
            'orders' => ['class' => SalesOrder::class, 'label' => 'Đơn hàng', 'icon' => 'bi-bag-check', 'module' => 'orders', 'show' => 'orders.show'],
            'invoices' => ['class' => Invoice::class, 'label' => 'Hóa đơn', 'icon' => 'bi-receipt', 'module' => 'invoices', 'show' => 'invoices.show'],
            'products' => ['class' => Product::class, 'label' => 'Sản phẩm', 'icon' => 'bi-box-seam', 'module' => 'products', 'show' => 'products.show'],
            'suppliers' => ['class' => \App\Models\Supplier::class, 'label' => 'Nhà cung cấp', 'icon' => 'bi-truck', 'module' => 'suppliers', 'show' => 'suppliers.show'],
            'projects' => ['class' => \App\Models\Project::class, 'label' => 'Dự án', 'icon' => 'bi-kanban', 'module' => 'projects', 'show' => 'projects.show'],
            'documents' => ['class' => \App\Models\Document::class, 'label' => 'Tài liệu', 'icon' => 'bi-folder2-open', 'module' => 'documents', 'permission' => 'documents.manage', 'show' => 'documents.show'],
            'tasks' => ['class' => Task::class, 'label' => 'Công việc', 'icon' => 'bi-check2-square', 'module' => 'tasks', 'show' => 'tasks.show'],
            'tickets' => ['class' => SupportTicket::class, 'label' => 'Ticket hỗ trợ', 'icon' => 'bi-headset', 'module' => 'tickets', 'show' => 'tickets.show'],
            'campaigns' => ['class' => Campaign::class, 'label' => 'Chiến dịch', 'icon' => 'bi-megaphone', 'module' => 'campaigns', 'show' => 'campaigns.show'],
        ];
    }

    /** Loại người dùng được xem trong thùng rác (có quyền xóa module đó). */
    public static function allowedTypes(User $user): array
    {
        return array_keys(array_filter(self::types(), fn ($t) => $user->can($t['permission'] ?? $t['module'].'.delete')));
    }

    private function query(string $type, User $user): Builder
    {
        $class = self::types()[$type]['class'];
        $query = $class::onlyTrashed();

        // Sản phẩm là danh mục chung của tổ chức (không có người phụ trách); các loại khác lọc theo phạm vi xem
        return in_array($class, [Product::class, \App\Models\Supplier::class], true) ? $query : $query->visibleTo($user);
    }

    /** Tên hiển thị của một bản ghi bất kỳ trong thùng rác. */
    public static function titleOf($model): string
    {
        return (string) ($model->name ?? $model->title ?? $model->full_name ?? $model->subject ?? $model->number ?? '#'.$model->getKey());
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $allowed = self::allowedTypes($user);
        abort_if($allowed === [], 403);
        $request->validate(['type' => ['nullable', Rule::in($allowed)]]);

        $counts = collect($allowed)->mapWithKeys(fn ($t) => [$t => $this->query($t, $user)->count()])->all();
        $type = $request->input('type') ?: (collect($counts)->filter()->keys()->first() ?? $allowed[0]);
        $items = $this->query($type, $user)->latest('deleted_at')->paginate($this->perPage($request))->withQueryString();

        // Người xóa: lấy từ nhật ký hoạt động (lần xóa gần nhất của từng bản ghi)
        $deleters = ActivityLog::with('user:id,name')->where('action', 'deleted')
            ->where('subject_type', (new (self::types()[$type]['class']))->getMorphClass())->whereIn('subject_id', $items->pluck('id'))
            ->latest('created_at')->get()->unique('subject_id')->keyBy('subject_id');

        return view('trash.index', [
            'types' => self::types(), 'counts' => $counts, 'type' => $type, 'items' => $items, 'deleters' => $deleters,
            'currentOrganization' => app(\App\Support\Tenancy\CurrentOrganization::class)->get(),
        ]);
    }

    public function restore(Request $request, string $type, int $id)
    {
        $user = $request->user();
        abort_unless(in_array($type, self::allowedTypes($user), true), 403);
        $model = $this->query($type, $user)->findOrFail($id);
        $this->authorize('delete', $model);   // cùng điều kiện với nút xóa (quyền + quyền sở hữu)

        $model->restore();

        return back()->with('success', __('Đã khôi phục ":item" từ thùng rác.', ['item' => self::titleOf($model)]));
    }
}
