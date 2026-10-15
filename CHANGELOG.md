# Nhật ký thay đổi — TOP CRM

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/). Ngày theo giờ Việt Nam.

## [1.0.0] — Phase 1 Foundation — 17/10/2026

### Thêm mới
- **1.0 MariaDB 10.11 LTS** (từ 10.4.32): dữ liệu khớp 100%, chỉ lắng nghe `127.0.0.1`. Hướng dẫn hoàn tác: `docs/MARIADB_UPGRADE.md`.
- **1.1 Khóa trang /setup vĩnh viễn** sau khi có quản trị đầu tiên (cờ trong DB + khóa đồng thời; đã thử 12 yêu cầu song song → chỉ 1 tài khoản). Phiên đăng nhập được mã hóa.
- **1.2 Song ngữ Việt / Anh** cho toàn bộ giao diện (~1.300 câu), bộ chọn ngôn ngữ, lưu theo tài khoản, email theo ngôn ngữ người nhận, trang pháp lý tiếng Anh.
- **1.3 Tổ chức (multi-tenancy):** bảng `organizations`, `organization_user`, cột `organization_id` trên 21 bảng nghiệp vụ, mã/unique theo tổ chức.
- **1.4 Cách ly dữ liệu theo tổ chức:** global scope fail-closed, chặn đổi tổ chức của bản ghi, `TenantRule` cho validation, nhắc việc theo tổ chức.
- **1.5 Phân quyền 2 tầng:** quản trị nền tảng + 14 vai trò riêng mỗi tổ chức (spatie teams).
- **1.6 Không gian làm việc:** chuyển tổ chức, quản lý tổ chức (nền tảng), thêm tài khoản có sẵn bằng email, tạm ngưng / gỡ thành viên.
- **1.7 Bố cục:** ô tìm chức năng trong menu, giao diện sáng/tối lưu theo tài khoản, sửa tràn ngang trên điện thoại.
- **1.8 Bảo mật tài khoản:** xác thực 2 lớp (TOTP + mã khôi phục), lịch sử đăng nhập, cảnh báo thiết bị mới, xác minh email, quy định mật khẩu chung, tùy chọn bắt buộc 2FA cho quản trị.
- **1.9 API bằng khóa (Sanctum):** khóa gắn tổ chức, 6 phạm vi, có thời hạn, quyền không vượt người tạo, trang quản lý/thu hồi khóa.
- **1.10 Tài liệu:** `ARCHITECTURE.md` (cập nhật), `DATABASE.md` (ERD), `SECURITY.md`, `TESTING.md`, `CHANGELOG.md`; script `scripts/generate-sql.ps1` và file SQL mới.
- Sao lưu một chạm `SAO_LUU.bat` (database, tệp tải lên, `.env`, Git) + kho GitHub riêng tư.

### Thay đổi
- Đổi tên vai trò cũ (giữ nguyên quyền và người dùng): Super Admin → Organization Owner + cờ quản trị nền tảng; Administrator → Organization Administrator; Sales Staff → Sales Employee; Marketing Staff → Marketing Employee; Customer Support → Support Agent; Viewer → Read-only Auditor.
- Mật khẩu mới tối thiểu 10 ký tự (trước đây 8 ở một số nơi); mật khẩu đang dùng vẫn hợp lệ.
- `/api/v1` chuyển sang `auth:sanctum` (vẫn hỗ trợ phiên web cùng tên miền); mỗi route yêu cầu phạm vi tương ứng.
- Middleware xác định tổ chức chạy trước khi tìm bản ghi trên route (web và API).

### Bảo mật
- Khóa bí mật 2FA và secret OAuth mã hóa bằng `APP_KEY`; mã khôi phục, khóa API, cookie thiết bị chỉ lưu băm.
- Nhật ký hoạt động không ghi mật khẩu, mã 2FA, khóa API.
- `composer audit` và `npm audit`: 0 lỗ hổng (17/10/2026).

### Hạn chế đã biết (chuyển sang Phase 2)
- Cài đặt hệ thống và phòng ban của người dùng còn dùng chung giữa các tổ chức.
- Chưa cấu hình gửi email thật (thư được ghi vào `storage/logs/laravel.log`).
- Mã nghiệp vụ dựa trên id toàn hệ thống (không liên tục trong từng tổ chức).
- Nội dung nhật ký/thông báo đã lưu bằng tiếng Việt.

## [0.9.0] — Bản CRM đầy đủ module — 13–15/10/2026
- Khách hàng, liên hệ, leads (chấm điểm, chuyển đổi), pipeline Kanban, công việc, báo giá → đơn hàng → hóa đơn → thanh toán (PDF), sản phẩm, marketing, hỗ trợ khách hàng, 8 báo cáo, thông báo, nhật ký, cài đặt.
- Trang đăng nhập thương hiệu TOP (máy tính + điện thoại), đăng nhập Google/Facebook cho tài khoản đã cấp.
- Giao diện kính kiểu macOS, sáng/tối, dashboard dữ liệu thật.
