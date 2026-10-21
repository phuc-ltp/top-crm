<?php

namespace App\Support;

use App\Models\User;

/**
 * Menu sidebar — nhóm có thể thu gọn. Mỗi mục chỉ hiển thị khi người dùng có quyền;
 * nhóm không còn mục con nào thì ẩn. Chỉ liệt kê module đã có chức năng thật.
 *
 * Nhãn viết bằng tiếng Việt và được dịch khi hiển thị (lang/en.json); id nhóm tính từ nhãn gốc nên không đổi theo ngôn ngữ.
 *
 * Định dạng mục: [nhãn, icon, route, quyền, mẫu route active] hoặc
 *               [nhãn, icon, null, null, null, [mục con...]]
 */
class Menu
{
    private static function definition(): array
    {
        return [
            null => [
                ['Tổng quan', 'bi-grid-1x2-fill', 'dashboard', 'dashboard.view', 'dashboard'],
            ],
            'Khách hàng & Bán hàng' => [
                ['Khách hàng', 'bi-people-fill', null, null, null, [
                    ['Danh sách khách hàng', null, 'customers.index', 'customers.view', 'customers.*'],
                    ['Người liên hệ', null, 'contacts.index', 'contacts.view', 'contacts.*'],
                ]],
                ['Leads', 'bi-funnel-fill', 'leads.index', 'leads.view', 'leads.*'],
                ['Cơ hội bán hàng', 'bi-kanban-fill', null, null, null, [
                    ['Pipeline (Kanban)', null, 'opportunities.index', 'opportunities.view', 'opportunities.index'],
                    ['Danh sách cơ hội', null, 'opportunities.list', 'opportunities.view', ['opportunities.list', 'opportunities.show', 'opportunities.create', 'opportunities.edit']],
                ]],
                ['Bán hàng', 'bi-bag-check-fill', null, null, null, [
                    ['Báo giá', null, 'quotations.index', 'quotations.view', 'quotations.*'],
                    ['Đơn hàng', null, 'orders.index', 'orders.view', 'orders.*'],
                    ['Hóa đơn', null, 'invoices.index', 'invoices.view', 'invoices.*'],
                    ['Đối soát thanh toán', null, 'payments.index', 'payments-reconcile', 'payments.index'],
                    ['Chỉ tiêu doanh số', null, 'targets.index', 'reports.view', 'targets.*'],
                    ['Mẫu báo giá', null, 'quotation-templates.index', 'quotations.approve', 'quotation-templates.*'],
                ]],
                ['Sản phẩm & Dịch vụ', 'bi-box-seam-fill', null, null, null, [
                    ['Danh sách sản phẩm', null, 'products.index', 'products.view', 'products.*'],
                    ['Nhà cung cấp', null, 'suppliers.index', 'suppliers.view', 'suppliers.*'],
                    ['Bảng giá', null, 'price-lists.index', 'products.update', 'price-lists.*'],
                ]],
                ['Kho hàng', 'bi-boxes', null, null, null, [
                    ['Tồn kho', null, 'inventory.index', 'inventory.view', 'inventory.index'],
                    ['Phiếu kho', null, 'stock-documents.index', 'inventory.view', 'stock-documents.*'],
                    ['Sổ kho', null, 'inventory.movements', 'inventory.view', 'inventory.movements'],
                    ['Danh mục kho', null, 'warehouses.index', 'inventory.manage', 'warehouses.*'],
                ]],
            ],
            'Vận hành' => [
                ['Công việc', 'bi-check2-square', null, null, null, [
                    ['Dự án', null, 'projects.index', 'projects.view', 'projects.*'],
                    ['Danh sách công việc', null, 'tasks.index', 'tasks.view', ['tasks.index', 'tasks.show', 'tasks.create', 'tasks.edit']],
                    ['Bảng Kanban', null, 'tasks.board', 'tasks.view', 'tasks.board'],
                    ['Lịch chung', null, 'calendar.index', 'dashboard.view', 'calendar.*'],
                    ['Lịch công việc', null, 'tasks.calendar', 'tasks.view', 'tasks.calendar'],
                    ['Khối lượng công việc', null, 'tasks.workload', 'tasks.view', 'tasks.workload'],
                    ['Hoạt động & lịch hẹn', null, 'activities.index', 'dashboard.view', 'activities.*'],
                ]],
                ['Chấm công', 'bi-fingerprint', null, null, null, [
                    ['Chấm công của tôi', null, 'attendance.index', 'dashboard.view', 'attendance.index'],
                    ['Tăng ca', null, 'overtime.index', 'dashboard.view', 'overtime.*'],
                    ['Bảng công tháng', null, 'attendance.board', 'payroll.view_all', ['attendance.board', 'attendance.records.*']],
                    ['Cài đặt chấm công', null, 'attendance.settings', 'payroll.manage', 'attendance.settings'],
                ]],
                ['Tài liệu', 'bi-folder2-open', 'documents.index', 'documents.view', 'documents.*'],
                ['Marketing', 'bi-megaphone-fill', 'campaigns.index', 'campaigns.view', 'campaigns.*'],
                ['Hỗ trợ khách hàng', 'bi-headset', null, null, null, [
                    ['Danh sách ticket', null, 'tickets.index', 'tickets.view', ['tickets.index', 'tickets.show', 'tickets.create', 'tickets.edit']],
                    ['Phân tích hỗ trợ', null, 'tickets.analytics', 'tickets.view', 'tickets.analytics'],
                    ['Danh mục ticket', null, 'ticket-categories.index', 'tickets.assign', 'ticket-categories.*'],
                ]],
                ['Báo cáo', 'bi-bar-chart-line-fill', 'reports.index', 'reports.view', 'reports.*'],
            ],
            'Quản trị' => [
                ['Nhân sự', 'bi-person-badge-fill', null, null, null, [
                    ['Người dùng', null, 'users.index', 'users.view', 'users.*'],
                    ['Danh bạ nhân viên', null, 'employees.index', 'employees.view', ['employees.index', 'employees.show', 'employees.edit']],
                    ['Sơ đồ tổ chức', null, 'employees.chart', 'employees.view', 'employees.chart'],
                    ['Nhóm', null, 'teams.index', 'teams.view', 'teams.*'],
                    ['Phòng ban', null, 'departments.index', 'users.manage', 'departments.*'],
                    ['Chi nhánh', null, 'branches.index', 'teams.manage', 'branches.*'],
                    ['Bậc lương', null, 'pay-grades.index', 'payroll.manage', 'pay-grades.*'],
                    ['Vai trò & quyền', null, 'roles.index', 'users.manage', 'roles.*'],
                ]],
                ['Hệ thống', 'bi-gear-fill', null, null, null, [
                    ['Cài đặt tổ chức', null, 'settings.edit', 'settings.manage', 'settings.edit'],
                    ['Trường tùy biến', null, 'settings.custom-fields', 'settings.manage', 'settings.custom-fields*'],
                    ['Cấu hình lead', null, 'settings.leads', 'settings.manage', 'settings.leads*'],
                    ['Cấu hình pipeline', null, 'settings.pipelines', 'settings.manage', 'settings.pipelines*'],
                    ['Đăng nhập & thương hiệu', null, 'settings.branding', 'platform', 'settings.branding'],
                    ['Nhật ký hoạt động', null, 'activity-logs.index', 'activity_logs.view', 'activity-logs.*'],
                    ['Thùng rác', null, 'trash.index', 'trash', 'trash.*'],
                    ['Design System', null, 'design-system', 'settings.manage', 'design-system'],
                    ['Quản lý tổ chức', null, 'platform.organizations.index', 'platform', 'platform.*'],
                ]],
            ],
        ];
    }

    public static function for(User $user): array
    {
        $result = [];
        foreach (self::definition() as $section => $items) {
            $visible = [];
            foreach ($items as $def) {
                if (! empty($def[5])) {
                    $children = [];
                    foreach ($def[5] as $child) {
                        if ($user->can($child[3])) {
                            $children[] = ['label' => __($child[0]), 'route' => $child[2], 'active' => request()->routeIs($child[4])];
                        }
                    }
                    if ($children) {
                        $visible[] = [
                            'label' => __($def[0]), 'icon' => $def[1], 'children' => $children,
                            'active' => collect($children)->contains('active', true),
                            'id' => 'nav-'.substr(md5($def[0]), 0, 8),
                        ];
                    }
                } elseif ($user->can($def[3])) {
                    $visible[] = ['label' => __($def[0]), 'icon' => $def[1], 'route' => $def[2], 'active' => request()->routeIs($def[4]), 'children' => []];
                }
            }
            if ($visible) {
                $result[] = ['title' => $section ? __($section) : null, 'items' => $visible];
            }
        }

        return $result;
    }

    /** Danh sách phẳng (dùng cho kiểm thử: mọi route trong menu phải mở được). */
    public static function flatRoutes(User $user): array
    {
        $routes = [];
        foreach (self::for($user) as $section) {
            foreach ($section['items'] as $item) {
                if ($item['children']) {
                    foreach ($item['children'] as $c) {
                        $routes[] = $c['route'];
                    }
                } else {
                    $routes[] = $item['route'];
                }
            }
        }

        return $routes;
    }
}
