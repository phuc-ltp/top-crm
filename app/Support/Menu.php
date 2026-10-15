<?php

namespace App\Support;

use App\Models\User;

/**
 * Menu sidebar — nhóm có thể thu gọn. Mỗi mục chỉ hiển thị khi người dùng có quyền;
 * nhóm không còn mục con nào thì ẩn. Chỉ liệt kê module đã có chức năng thật.
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
                    ['Chỉ tiêu doanh số', null, 'targets.index', 'reports.view', 'targets.*'],
                ]],
                ['Sản phẩm & Dịch vụ', 'bi-box-seam-fill', 'products.index', 'products.view', 'products.*'],
            ],
            'Vận hành' => [
                ['Công việc', 'bi-check2-square', null, null, null, [
                    ['Danh sách công việc', null, 'tasks.index', 'tasks.view', ['tasks.index', 'tasks.show', 'tasks.create', 'tasks.edit']],
                    ['Lịch công việc', null, 'tasks.calendar', 'tasks.view', 'tasks.calendar'],
                ]],
                ['Marketing', 'bi-megaphone-fill', 'campaigns.index', 'campaigns.view', 'campaigns.*'],
                ['Hỗ trợ khách hàng', 'bi-headset', 'tickets.index', 'tickets.view', 'tickets.*'],
                ['Báo cáo', 'bi-bar-chart-line-fill', 'reports.index', 'reports.view', 'reports.*'],
            ],
            'Quản trị' => [
                ['Nhân sự', 'bi-person-badge-fill', null, null, null, [
                    ['Người dùng', null, 'users.index', 'users.view', 'users.*'],
                    ['Phòng ban', null, 'departments.index', 'users.manage', 'departments.*'],
                    ['Vai trò & quyền', null, 'roles.index', 'users.manage', 'roles.*'],
                ]],
                ['Hệ thống', 'bi-gear-fill', null, null, null, [
                    ['Cài đặt chung', null, 'settings.edit', 'settings.manage', 'settings.edit'],
                    ['Đăng nhập & thương hiệu', null, 'settings.branding', 'settings.manage', 'settings.branding'],
                    ['Nhật ký hoạt động', null, 'activity-logs.index', 'activity_logs.view', 'activity-logs.*'],
                    ['Design System', null, 'design-system', 'settings.manage', 'design-system'],
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
                            $children[] = ['label' => $child[0], 'route' => $child[2], 'active' => request()->routeIs($child[4])];
                        }
                    }
                    if ($children) {
                        $visible[] = [
                            'label' => $def[0], 'icon' => $def[1], 'children' => $children,
                            'active' => collect($children)->contains('active', true),
                            'id' => 'nav-'.substr(md5($def[0]), 0, 8),
                        ];
                    }
                } elseif ($user->can($def[3])) {
                    $visible[] = ['label' => $def[0], 'icon' => $def[1], 'route' => $def[2], 'active' => request()->routeIs($def[4]), 'children' => []];
                }
            }
            if ($visible) {
                $result[] = ['title' => $section ?: null, 'items' => $visible];
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
