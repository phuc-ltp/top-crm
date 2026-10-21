<?php

namespace App\Support;

/**
 * Danh mục loại thông báo: nhóm, nhãn, có cho người dùng tắt không.
 * Thông báo bảo mật luôn gửi (không tắt được). Loại không có trong danh mục thuộc nhóm "Khác".
 */
class NotificationCatalog
{
    /** nhóm => [nhãn, icon, màu] */
    public const GROUPS = [
        'sales' => ['Bán hàng & hóa đơn', 'bi-receipt', 'orange'],
        'customers' => ['Khách hàng & lead', 'bi-people', 'blue'],
        'work' => ['Công việc & lịch hẹn', 'bi-check2-square', 'purple'],
        'inventory' => ['Kho hàng', 'bi-boxes', 'green'],
        'support' => ['Hỗ trợ khách hàng', 'bi-headset', 'yellow'],
        'security' => ['Bảo mật tài khoản', 'bi-shield-lock', 'red'],
        'other' => ['Khác', 'bi-bell', 'gray'],
    ];

    /** loại => [nhóm, nhãn] */
    public const KINDS = [
        'assigned' => ['customers', 'Được phân công khách hàng'],
        'lead_assigned' => ['customers', 'Được phân công lead'],
        'deal_stage' => ['customers', 'Cơ hội đổi giai đoạn'],
        'task_assigned' => ['work', 'Được giao công việc / lịch hẹn'],
        'task_due' => ['work', 'Công việc / lịch hẹn sắp đến hạn'],
        'task_overdue' => ['work', 'Công việc quá hạn'],
        'task_comment' => ['work', 'Bình luận mới trong công việc'],
        'project_member' => ['work', 'Được thêm vào dự án'],
        'overtime_request' => ['work', 'Đề nghị tăng ca cần duyệt'],
        'overtime_decision' => ['work', 'Kết quả duyệt tăng ca'],
        'payslip_ready' => ['work', 'Phiếu lương đã sẵn sàng'],
        'asset_assigned' => ['work', 'Được bàn giao tài sản'],
        'asset_overdue' => ['work', 'Tài sản mượn quá hạn'],
        'license_expiring' => ['work', 'Giấy phép phần mềm sắp hết hạn'],
        'order_approved' => ['sales', 'Đơn hàng được duyệt'],
        'quotation_approval' => ['sales', 'Duyệt báo giá (chờ duyệt, kết quả duyệt)'],
        'quotation_expired' => ['sales', 'Báo giá hết hạn'],
        'invoice_due' => ['sales', 'Hóa đơn sắp đến hạn thanh toán'],
        'invoice_overdue' => ['sales', 'Hóa đơn quá hạn thanh toán'],
        'payment_received' => ['sales', 'Có khoản thanh toán mới'],
        'invoice_paid' => ['sales', 'Hóa đơn đã thu đủ'],
        'low_stock' => ['inventory', 'Tồn kho thấp'],
        'ticket_new' => ['support', 'Ticket hỗ trợ mới'],
        'ticket_sla_warning' => ['support', 'Ticket sắp trễ SLA'],
        'ticket_escalated' => ['support', 'Ticket vi phạm SLA (leo thang)'],
        'security' => ['security', 'Cảnh báo bảo mật (đăng nhập lạ, đổi quyền...)'],
    ];

    /** Loại không cho tắt. */
    public const MANDATORY = ['security'];

    public static function group(?string $kind): string
    {
        return self::KINDS[$kind][0] ?? 'other';
    }

    /** [nhãn, icon, màu] của nhóm chứa loại thông báo. */
    public static function groupMeta(?string $kind): array
    {
        return self::GROUPS[self::group($kind)];
    }

    public static function mandatory(string $kind): bool
    {
        return in_array($kind, self::MANDATORY, true);
    }

    /** Có cấu hình gửi thư thật (không phải chế độ ghi log / thử nghiệm). */
    public static function mailEnabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true) && filled(config('mail.from.address'));
    }
}
