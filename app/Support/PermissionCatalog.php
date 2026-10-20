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
        'projects' => ['label' => 'Dự án', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'products' => ['label' => 'Sản phẩm', 'actions' => ['view', 'create', 'update', 'delete', 'export']],
        'suppliers' => ['label' => 'Nhà cung cấp', 'actions' => ['view', 'create', 'update', 'delete', 'export']],
        'inventory' => ['label' => 'Kho hàng', 'actions' => ['view', 'create', 'adjust', 'manage']],
        'quotations' => ['label' => 'Báo giá', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'approve']],
        'orders' => ['label' => 'Đơn hàng', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'approve']],
        'invoices' => ['label' => 'Hóa đơn & thanh toán', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'campaigns' => ['label' => 'Marketing', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export']],
        'tickets' => ['label' => 'Hỗ trợ khách hàng', 'actions' => ['view', 'view_all', 'create', 'update', 'delete', 'export', 'assign']],
        'reports' => ['label' => 'Báo cáo', 'actions' => ['view', 'export']],
        'users' => ['label' => 'Người dùng & vai trò', 'actions' => ['view', 'create', 'update', 'delete', 'manage']],
        'employees' => ['label' => 'Hồ sơ nhân viên', 'actions' => ['view', 'view_sensitive', 'update', 'export']],
        'teams' => ['label' => 'Nhóm & chi nhánh', 'actions' => ['view', 'manage']],
        'activity_logs' => ['label' => 'Nhật ký hoạt động', 'actions' => ['view']],
        'settings' => ['label' => 'Cài đặt hệ thống', 'actions' => ['manage']],
        'api' => ['label' => 'Kết nối API', 'actions' => ['use']],
    ];

    public const ACTION_LABELS = [
        'view' => 'Xem', 'view_all' => 'Xem tất cả', 'create' => 'Tạo', 'update' => 'Sửa', 'delete' => 'Xóa',
        'export' => 'Xuất', 'assign' => 'Phân công', 'approve' => 'Duyệt', 'manage' => 'Quản trị', 'use' => 'Sử dụng', 'adjust' => 'Điều chỉnh', 'view_sensitive' => 'Xem thông tin nhạy cảm',
    ];

    /** Vai trò cấp nền tảng: không phải vai trò spatie mà là cờ users.is_platform_admin (toàn quyền mọi tổ chức). */
    public const PLATFORM_ROLE = 'Platform Super Administrator';

    /** Vai trò chủ sở hữu tổ chức (luôn có toàn quyền trong tổ chức; không chỉnh được). */
    public const OWNER = 'Organization Owner';

    /** 14 vai trò tổ chức — mỗi tổ chức có bản riêng (spatie teams, team = organization_id). */
    public const ORG_ROLES = [
        'Organization Owner', 'Organization Administrator',
        'Sales Manager', 'Sales Employee',
        'Marketing Manager', 'Marketing Employee',
        'Customer Support Manager', 'Support Agent',
        'Finance Manager', 'Accountant',
        'Inventory Manager', 'Project Manager',
        'Employee', 'Read-only Auditor',
    ];

    /** Vai trò được bảo vệ: không chỉnh quyền / không xóa. */
    public const PROTECTED_ROLES = ['Organization Owner'];

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

    /** Ma trận quyền mặc định. Quản trị nền tảng có toàn quyền qua Gate::before (users.is_platform_admin). */
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

        $permissions = match ($role) {
            'Organization Owner', 'Organization Administrator' => $all,
            'Sales Manager' => $pick([
                'dashboard' => '*', 'customers' => '*', 'contacts' => '*', 'leads' => '*', 'opportunities' => '*',
                'tasks' => '*', 'quotations' => '*', 'orders' => '*', 'invoices' => '*',
                'products' => ['view', 'export'], 'campaigns' => ['view', 'view_all'], 'tickets' => ['view', 'view_all'],
                'reports' => '*', 'users' => ['view'], 'suppliers' => ['view'], 'inventory' => ['view'],
                'projects' => ['view', 'create', 'update'],
            ]),
            'Sales Employee' => $pick([
                'dashboard' => '*',
                'customers' => ['view', 'create', 'update'],
                'contacts' => ['view', 'create', 'update'],
                'leads' => ['view', 'create', 'update'],
                'opportunities' => ['view', 'create', 'update'],
                'tasks' => ['view', 'create', 'update'],
                'quotations' => ['view', 'create', 'update'],
                'orders' => ['view', 'create', 'update'],
                'invoices' => ['view'],
                'products' => ['view'], 'inventory' => ['view'],
                'tickets' => ['view', 'create'],
                'projects' => ['view'],
            ]),
            'Marketing Employee' => $pick([
                'dashboard' => '*', 'campaigns' => '*',
                'leads' => ['view', 'view_all', 'create', 'update', 'export', 'assign'],
                'customers' => ['view', 'view_all', 'export'],
                'contacts' => ['view', 'view_all'],
                'products' => ['view'],
                'tasks' => ['view', 'create', 'update'],
                'reports' => ['view'],
                'projects' => ['view'],
            ]),
            'Support Agent' => $pick([
                'dashboard' => '*', 'tickets' => '*',
                'customers' => ['view', 'view_all', 'update'],
                'contacts' => ['view', 'view_all', 'create', 'update'],
                'products' => ['view'],
                'tasks' => ['view', 'create', 'update'],
                'reports' => ['view'],
                'projects' => ['view'],
            ]),
            'Read-only Auditor' => $pick([
                'dashboard' => ['view'],
                'customers' => ['view', 'view_all'], 'contacts' => ['view', 'view_all'], 'leads' => ['view', 'view_all'],
                'opportunities' => ['view', 'view_all'], 'tasks' => ['view', 'view_all'], 'products' => ['view'],
                'quotations' => ['view', 'view_all'], 'orders' => ['view', 'view_all'], 'invoices' => ['view', 'view_all'],
                'campaigns' => ['view', 'view_all'], 'tickets' => ['view', 'view_all'], 'reports' => ['view'],
                'activity_logs' => ['view'], 'suppliers' => ['view'], 'inventory' => ['view'],
                'projects' => ['view', 'view_all'],
            ]),
            'Marketing Manager' => $pick([
                'dashboard' => '*', 'campaigns' => '*', 'leads' => '*', 'tasks' => '*', 'reports' => '*',
                'customers' => ['view', 'view_all', 'export'], 'contacts' => ['view', 'view_all', 'export'],
                'products' => ['view'], 'users' => ['view'], 'projects' => ['view', 'create', 'update'],
            ]),
            'Customer Support Manager' => $pick([
                'dashboard' => '*', 'tickets' => '*', 'contacts' => '*', 'tasks' => '*', 'reports' => '*',
                'customers' => ['view', 'view_all', 'update', 'export'], 'products' => ['view'], 'users' => ['view'],
                'projects' => ['view', 'create', 'update'],
            ]),
            'Finance Manager' => $pick([
                'dashboard' => '*', 'invoices' => '*', 'reports' => '*',
                'orders' => ['view', 'view_all', 'export', 'approve'], 'quotations' => ['view', 'view_all', 'export'],
                'customers' => ['view', 'view_all', 'export'], 'products' => ['view'], 'suppliers' => ['view', 'export'], 'inventory' => ['view'],
                'tasks' => ['view', 'create', 'update'], 'users' => ['view'], 'projects' => ['view'],
            ]),
            'Accountant' => $pick([
                'dashboard' => ['view'], 'invoices' => ['view', 'view_all', 'create', 'update', 'export'],
                'orders' => ['view', 'view_all'], 'customers' => ['view', 'view_all'], 'products' => ['view'], 'suppliers' => ['view'], 'inventory' => ['view'],
                'reports' => ['view', 'export'], 'tasks' => ['view', 'create', 'update'], 'projects' => ['view'],
            ]),
            'Inventory Manager' => $pick([
                'dashboard' => ['view'], 'products' => '*', 'suppliers' => '*', 'inventory' => '*', 'orders' => ['view', 'view_all'],
                'reports' => ['view'], 'tasks' => ['view', 'create', 'update'], 'projects' => ['view'],
            ]),
            'Project Manager' => $pick([
                'dashboard' => '*', 'tasks' => '*', 'projects' => '*', 'customers' => ['view', 'view_all'],
                'opportunities' => ['view', 'view_all'], 'reports' => ['view'], 'users' => ['view'],
            ]),
            'Employee' => $pick([
                'dashboard' => ['view'], 'tasks' => ['view', 'create', 'update'], 'projects' => ['view'],
                'customers' => ['view'], 'contacts' => ['view'], 'products' => ['view'],
            ]),
            default => [],
        };

        // Mọi vai trò còn lại đều xem được danh bạ nhân viên và nhóm (thông tin công việc, không gồm thông tin nhạy cảm)
        if ($permissions && ! in_array($role, ['Organization Owner', 'Organization Administrator'], true)) {
            $permissions = array_merge($permissions, ['employees.view', 'teams.view']);
        }

        return $permissions;
    }
}
