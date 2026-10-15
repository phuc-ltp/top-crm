# OrangeCRM Enterprise

Hệ thống quản lý quan hệ khách hàng (CRM) chạy trên máy tính Windows với XAMPP.

## Bắt đầu nhanh (3 bước)

1. Mở **XAMPP Control Panel** và bấm **Start** ở dòng **MySQL** (và **Apache** nếu muốn dùng địa chỉ `http://localhost/orangecrm`).
2. Bấm đúp vào tệp **`START_CRM.bat`** trong thư mục `C:\phuc-crm`.
3. Trình duyệt tự mở. **Lần đầu tiên**, bạn sẽ thấy trang *Tạo tài khoản quản trị*, hãy tự đặt email và mật khẩu cho mình.

| Việc cần làm | Cách làm |
|---|---|
| Mở CRM | Bấm đúp `START_CRM.bat` |
| Tắt CRM | Bấm đúp `STOP_CRM.bat` |
| Kiểm tra lỗi | Bấm đúp `CHECK_CRM.bat` |

**Địa chỉ truy cập:**
- `http://127.0.0.1:8000`: luôn có khi đã chạy `START_CRM.bat`
- `http://localhost/orangecrm`: qua Apache của XAMPP (cần bật Apache)

## Chức năng

Bảng điều khiển với KPI và biểu đồ · Khách hàng (nhập/xuất CSV, Excel, cảnh báo trùng) · Người liên hệ · Leads (chấm điểm, chuyển đổi) · Pipeline Kanban kéo thả · Công việc (lịch, checklist, tệp đính kèm) · Báo giá, Đơn hàng, Hóa đơn, Thanh toán (xuất PDF) · Chỉ tiêu doanh số · Sản phẩm · Marketing · Hỗ trợ khách hàng · 8 loại báo cáo · Thông báo nội bộ · Người dùng, 7 vai trò, phân quyền chi tiết · Nhật ký hoạt động · Cài đặt hệ thống.

## Tài liệu

| Tài liệu | Nội dung |
|---|---|
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | Hướng dẫn sử dụng hằng ngày |
| [docs/ADMIN_GUIDE.md](docs/ADMIN_GUIDE.md) | Quản trị: người dùng, phân quyền, sao lưu |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Cài đặt trên máy mới |
| [docs/DATABASE_SETUP.md](docs/DATABASE_SETUP.md) | Database, file SQL, sao lưu và khôi phục |
| [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) | Xử lý sự cố |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Kiến trúc kỹ thuật (cho lập trình viên) |
| [docs/TEST_REPORT.md](docs/TEST_REPORT.md) | Kết quả kiểm thử |
| [docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md) | Triển khai lên máy chủ thật |
| [docs/MOBILE_UI.md](docs/MOBILE_UI.md) | Cấu trúc giao diện điện thoại, nơi ghép code mobile |

## Công nghệ

Laravel 12 (PHP 8.2) · MySQL/MariaDB · Blade · Bootstrap 5 · Bootstrap Icons · Chart.js · SortableJS · Vite.

> **Vì sao Laravel 12 thay vì 10?** Laravel 10 đã ngừng nhận bản vá bảo mật từ 04/02/2025. Laravel 12 có cách viết mã gần như giống hệt nhưng vẫn được hỗ trợ bảo mật. Xem chi tiết trong [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
