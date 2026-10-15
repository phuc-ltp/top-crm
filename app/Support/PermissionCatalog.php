<?php

namespace App\Support;

/**
 * Danh mục quyền theo module + hành động và ma trận quyền mặc định của các vai trò.
 * Quyền đặt tên dạng "{module}.{action}". "view_all" = được xem dữ liệu của mọi nhân viên.
 */
class PermissionCatalog
{
    public const MODULES = [
        'dashboard' => ['label' => 'Tổng quan', 'actions' => ['view']],
        'customers' => ['label' => 'Khách hàng', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'assign']],
        'contacts' => ['label' => 'Người liên hệ', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'leads' => ['label' => 'Leads', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'assign']],
        'opportunities' => ['label' => 'Cơ hội / Pipeline', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'assign']],
        'tasks' => ['label' => 'Công việc', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'assign']],
        'products' => ['label' => 'Sản phẩm', 'actions' => ['view', 'create', 'update', 'delete', 'export']],
        'quotations' => ['label' => 'Báo giá', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'approve']],
        'orders' => ['label' => 'Đơn hàng', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'approve']],
        'invoices' => ['label' => 'Hóa đơn & thanh toán', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'campaigns' => ['label' => 'Marketing', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'tickets' => ['label' => 'Hỗ trợ khách hàng', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'assign']],
        'reports' => ['label' => 'Báo cáo', 'actions' => ['view', 'export']],
        'users' => ['label' => 'Người dùng & vai trò', 'actions' => ['view', 'create', 'update', 'delete', 'manage']],
        'activity_logs' => ['label' => 'Nhật ký hoạt động', 'actions' => ['view']],
        'settings' => ['label' => 'Cài đặt hệ thống', 'actions' => ['manage']],
    ];

    public const ACTION_LABELS = [
        'view' => 'Xem', 'view_all' => 'Xem tất cả', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
        'export' => 'Xuất', 'assign' => 'Phân công', 'approve' => 'Duyệt', 'manage' => 'Quản trị',
    ];

    public const ROLES = [
        'Super Admin', 'Administrator', 'Sales Manager', 'Sales Staff', 'Marketing Staff', 'Customer Support', 'Viewer',
    ];

    public static function all(): array
    {
        $permissions = [];
        foreach (self::MODULES as $module => $def) {
            foreach ($def['actions'] as $action) {
                $permissions[] = "$module.$action";
            }
        }

        return $permissions;
    }

    /** Ma trận quyền mặc định. Super Admin có toàn quyền qua Gate::before. */
    public static function defaultsFor(string $role): array
    {
        $all = self::all();

        $pick = function (array $spec) {
            $out = [];
            foreach ($spec as $module => $actions) {
                $actions = $actions === '*' ? self::MODULES[$module]['actions'] : $actions;
                foreach ($actions as $a) {
                    $out[] = "$module.$a";
                }
            }

            return $out;
        };

        return match ($role) {
            'Super Admin', 'Administrator' => $all,
            'Sales Manager' => $pick([
                'dashboard' => '*', 'customers' => '*', 'contacts' => '*', 'leads' => '*', 'opportunities' => '*',
                'tasks' => '*', 'quotations' => '*', 'orders' => '*', 'invoices' => '*',
                'products' => ['view', 'export'], 'campaigns' => ['view', 'view_all'], 'tickets' => ['view', 'view_all'],
                'reports' => '*', 'users' => ['view'],
            ]),
            'Sales Staff' => $pick([
                'dashboard' => '*',
                'customers' => ['view', 'create', 'update'],
                'contacts' => ['view', 'create', 'update'],
                'leads' => ['view', 'create', 'update'],
                'opportunities' => ['view', 'create', 'update'],
                'tasks' => ['view', 'create', 'update'],
                'quotations' => ['view', 'create', 'update'],
                'orders' => ['view', 'create', 'update'],
                'invoices' => ['view'],
                'products' => ['view'],
                'tickets' => ['view', 'create'],
            ]),
            'Marketing Staff' => $pick([
                'dashboard' => '*', 'campaigns' => '*',
                'leads' => ['view', 'view_all', 'create', 'update', 'export', 'assign'],
                'customers' => ['view', 'view_all', 'export'],
                'contacts' => ['view', 'view_all'],
                'products' => ['view'],
                'tasks' => ['view', 'create', 'update'],
                'reports' => ['view'],
            ]),
            'Customer Support' => $pick([
                'dashboard' => '*', 'tickets' => '*',
                'customers' => ['view', 'view_all', 'update'],
                'contacts' => ['view', 'view_all', 'create', 'update'],
                'products' => ['view'],
                'tasks' => ['view', 'create', 'update'],
                'reports' => ['view'],
            ]),
            'Viewer' => $pick([
                'dashboard' => ['view'],
                'customers' => ['view', 'view_all'], 'contacts' => ['view', 'view_all'], 'leads' => ['view', 'view_all'],
                'opportunities' => ['view', 'view_all'], 'tasks' => ['view', 'view_all'], 'products' => ['view'],
                'quotations' => ['view', 'view_all'], 'orders' => ['view', 'view_all'], 'invoices' => ['view', 'view_all'],
                'campaigns' => ['view', 'view_all'], 'tickets' => ['view', 'view_all'], 'reports' => ['view'],
            ]),
            default => [],
        };
    }
}
