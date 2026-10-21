<?php

namespace App\Support;

/**
 * Các câu giao diện dùng trong JavaScript (resources/js/app.js, hàm t()).
 * Trang chèn bản dịch vào window.CRM_I18N; ở tiếng Việt không cần chèn (khóa = câu tiếng Việt).
 */
class JsTranslations
{
    public const KEYS = [
        'Đóng',
        'Đã sao chép mã khôi phục.',
        'Đã sao chép khóa API.',
        'Tìm gần đây',
        'giữ',
        'Xóa',
        'Xem tất cả kết quả cho “:q”',
        'Không sao chép được, hãy chép thủ công.',
        'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
        'Bạn không có quyền thực hiện thao tác này.',
        'Không tìm thấy dữ liệu.',
        'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
        'Bạn thao tác quá nhanh, vui lòng thử lại sau.',
        'Đã có lỗi xảy ra, vui lòng thử lại.',
        'Đang xử lý...',
        'Vui lòng kiểm tra lại các trường được đánh dấu.',
        'Xác nhận',
        'Bạn có chắc chắn muốn thực hiện thao tác này?',
        'Không tìm thấy kết quả phù hợp.',
        'Không thể tìm kiếm lúc này.',
        'Chưa có thông báo nào',
        'Chứng từ cần ít nhất một dòng.',
        'tỷ',
        'tr',
        'Hôm nay',
        'Thực thu',
        'Hóa đơn phát hành',
        'Thành công',
        'Đang xử lý',
        'Thất bại',
        // Dùng trong script của từng trang
        'Cảnh báo trùng:',
        'phụ trách:',
        'Sửa phòng ban',
        'Thêm phòng ban',
        'Sửa danh mục',
        'Thêm danh mục',
        'Sửa bậc lương',
        'Thêm bậc lương',
        'Đã chép',
        'Đã lấy vị trí (sai số ~:m m)',
        'Không lấy được vị trí. Hãy bật định vị và cho phép trình duyệt truy cập.',
        'Trình duyệt không hỗ trợ định vị.',
        'Sửa văn phòng',
        'Thêm văn phòng',
        'Sửa chi nhánh',
        'Thêm chi nhánh',
        'Nội dung mục checklist',
        'Đã lưu thành công.',
        'Bạn chắc chắn muốn xóa bản ghi này?',
        'Xóa',
    ];

    /** @return array<string,string> bản dịch theo ngôn ngữ hiện tại (rỗng nếu là tiếng Việt) */
    public static function forCurrentLocale(): array
    {
        if (app()->getLocale() === 'vi') {
            return [];
        }

        return collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => __($k)])->filter(fn ($v, $k) => $v !== $k)->all();
    }
}
