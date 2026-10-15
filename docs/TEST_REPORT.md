# Báo cáo kiểm thử OrangeCRM (bàn giao 14/10/2026)

> Báo cáo lịch sử. Phạm vi kiểm thử và kết quả hiện tại (190 test, hết Phase 1): xem [TESTING.md](TESTING.md).

**Ngày kiểm thử:** 14/10/2026
**Môi trường:** Windows 11 Pro · XAMPP · PHP 8.2.12 · MariaDB 10.4.32 · Laravel 12.69.3 · Node 24

## 1. Tổng hợp

| Loại kiểm thử | Kết quả |
|---|---|
| Kiểm thử tự động (PHPUnit): unit, feature, database | **77/77 đạt** (1.058 kiểm tra), cập nhật 14/10/2026 sau khi làm lại trang đăng nhập |
| Kiểm thử HTTP end-to-end trên server thật (cookie, CSRF, session) | **15/15 đạt** |
| Import file SQL vào database trống và so sánh với migration | **Đạt**: 46 bảng, 422 cột, 199 chỉ mục, 69 khóa ngoại, 0 khác biệt |
| Script START / STOP / CHECK_CRM.bat | **Đạt** |
| `composer audit` / `npm audit` (lỗ hổng thư viện) | **0 lỗ hổng** |
| Kiểm thử trình duyệt tự động (Selenium/Playwright) | **Không thực hiện được**: môi trường không có công cụ điều khiển trình duyệt. Đã thay bằng kiểm thử HTTP end-to-end và render toàn bộ trang |

Lệnh chạy lại: `php artisan test` (dùng database riêng `orangecrm_test`, không ảnh hưởng dữ liệu thật).

## 2. Chi tiết kiểm thử tự động

### Unit: BusinessLogicTest (7 test)
- Tính tiền chứng từ (thành tiền, chiết khấu, VAT, tổng) và chặn dữ liệu xấu (giá âm, giảm giá > 100%, chiết khấu > tạm tính)
- Chuẩn hóa số điện thoại (+84 → 0)
- Quy tắc chấm điểm lead (tối đa 100)
- Ma trận quyền 7 vai trò hợp lệ
- Hàm định dạng tiền VND, ngày DD/MM/YYYY, escape badge
- Sinh mã nghiệp vụ KH00001

### Feature: AuthTest (9 test)
- Trang `/setup` tạo Super Admin đầu tiên rồi tự khóa; từ chối mật khẩu yếu
- Đăng nhập và đăng xuất, ghi `last_login_at` và nhật ký
- Sai mật khẩu 5 lần thì bị khóa tạm (kể cả khi lần sau nhập đúng)
- Tài khoản bị khóa: không đăng nhập được, đang trong phiên thì bị đẩy ra
- Quên mật khẩu (không lộ email có tồn tại hay không) và đặt lại mật khẩu
- Đổi mật khẩu, cập nhật hồ sơ
- Mật khẩu và hash không bao giờ xuất hiện trong nhật ký

### Feature: AuthorizationTest (9 test)
- Viewer không tạo, sửa, xóa, xuất được (403)
- Sales Staff chỉ thấy khách hàng của mình (danh sách, chi tiết, API, tìm kiếm)
- Sales Staff không tự đổi người phụ trách; Sales Manager phân công hàng loạt và người được giao nhận thông báo
- Trang quản trị bị chặn với nhân viên
- Administrator không sửa, khóa, xóa được Super Admin và không gán được vai trò Super Admin
- Marketing không truy cập hóa đơn
- Middleware CSRF đang hoạt động
- Chỉnh ma trận quyền có hiệu lực ngay

### Feature: CustomerTest (7 test)
- CRUD đầy đủ, lưu trữ (xóa mềm) và khôi phục, nhật ký thay đổi
- Validation (422), cảnh báo trùng email/điện thoại và xác nhận vẫn tạo
- Tìm kiếm, lọc, phân trang; tham số sắp xếp độc hại bị bỏ qua
- Nhập CSV: bỏ qua dòng trùng, dòng thiếu tên, dòng sai định dạng
- Ghi chú / lịch sử tương tác
- Chống XSS (nội dung `<script>` được escape)

### Feature: LeadPipelineTest (6 test)
- CRUD lead, chấm điểm tự động, thông báo khi được phân công, không tự đặt "converted"
- Chuyển đổi lead thành khách hàng mới + cơ hội; liên kết khách có sẵn; chặn chuyển đổi 2 lần
- Kanban kéo thả qua AJAX: đổi giai đoạn, xác suất, `closed_at`, lịch sử, thông báo
- Nhân viên khác không kéo được thẻ của người khác (403 JSON); giai đoạn không tồn tại trả 422

### Feature: SalesFlowTest (6 test)
- **Tổng tiền tính ở backend**, giá trị giả mạo từ trình duyệt bị bỏ qua
- Luồng đầy đủ: Báo giá → Đơn hàng → Duyệt → Hóa đơn nháp → Phát hành → Thanh toán một phần → Đủ; chặn thanh toán vượt; xóa thanh toán thì tính lại; doanh thu Dashboard khớp
- Không lập hóa đơn trùng; hóa đơn đã phát hành không sửa được; quy tắc hủy hóa đơn
- Sales Staff không tự duyệt đơn
- Chỉ tiêu doanh số

### Feature: OperationsTest (8 test)
- Công việc: giao việc, checklist, bình luận, tải lên/tải xuống tệp, chặn tệp `.exe`, hoàn thành
- Người không liên quan không tải được tệp đính kèm
- Nhắc việc sắp đến hạn và tự chuyển "Quá hạn" (không nhắc lặp)
- Chiến dịch: gắn lead, tỷ lệ chuyển đổi, chi phí/lead
- Ticket: phản hồi, ghi chú nội bộ, giải quyết
- Quản lý người dùng: tạo, khóa, đặt mật khẩu, gửi liên kết đặt lại, xóa; không tự xóa mình
- Cài đặt hệ thống, bật/tắt thông báo
- Thông báo: mở, đánh dấu đã đọc

### Feature: DatabaseTest (10 test)
- Đủ 31 bảng bắt buộc; toàn bộ dùng InnoDB và utf8mb4
- Khóa ngoại chặn bản ghi mồ côi; RESTRICT khi xóa khách có hóa đơn; CASCADE và SET NULL
- Ràng buộc unique (SKU)
- Quan hệ Eloquent; tổng chứng từ khớp tổng các dòng
- Seeder: 7 vai trò, 6 giai đoạn, không nạp demo khi tắt; demo đủ số lượng yêu cầu; chạy lại không nhân đôi; tài khoản demo không đoán được mật khẩu
- `crm:purge-demo` chỉ xóa dữ liệu demo
- Migration rollback và chạy lại

### Feature: LoginPageTest (9 test, thêm 14/10/2026)
- Trang đăng nhập hiển thị thương hiệu, chân trang, liên kết
- Đăng nhập bằng tên đăng nhập (không phân biệt hoa thường), từ chối tên sai
- Google/Facebook: chưa cấu hình thì báo, đã cấu hình thì chuyển đúng tới Google
- 3 trang Điều khoản / Bảo mật / Hỗ trợ (chống XSS nội dung tùy chỉnh)
- Cài đặt thương hiệu: lưu, tải ảnh nền, **khóa bí mật được mã hóa**, để trống thì giữ khóa cũ; nhân viên không truy cập được
- Tên đăng nhập là duy nhất

### Feature: PageSmokeTest (6 test)
- Render **38 trang** danh sách/tạo mới, **trang chi tiết và trang sửa** của 12 module, **8 báo cáo** (+ xuất CSV/XLSX/PDF), PDF báo giá/đơn hàng/hóa đơn, xuất 10 loại dữ liệu
- AJAX: dữ liệu biểu đồ, tìm kiếm, thông báo, REST API
- Mỗi vai trò mở được Dashboard và **mọi mục menu** của mình

## 3. Kiểm thử HTTP end-to-end (server thật, database thử nghiệm riêng)

| # | Kịch bản | Kết quả |
|---|---|---|
| 1 | Setup tạo Super Admin và vào Dashboard | Đạt |
| 2 | POST thiếu CSRF token bị chặn (419) | Đạt |
| 3 | Tạo khách hàng qua form, ghi MySQL, trang chi tiết có mã KH | Đạt |
| 4 | Tìm kiếm khách hàng | Đạt |
| 5 | Phân trang | Đạt |
| 6 | AJAX dữ liệu biểu đồ Dashboard | Đạt |
| 7 | AJAX tìm kiếm toàn cục | Đạt |
| 8 | Kanban kéo thả (PATCH + CSRF header) | Đạt |
| 9 | Validation trả 422 | Đạt |
| 10 | Tải PDF báo giá | Đạt |
| 11 | Xuất Excel | Đạt |
| 12 | Mở 23 trang menu (HTTP 200) | Đạt |
| 13 | Đăng xuất | Đạt |
| 14 | Từ chối mật khẩu sai | Đạt |
| 15 | Đăng nhập lại | Đạt |

## 4. Lỗi phát hiện và đã sửa trong quá trình kiểm thử

| Lỗi | Nguyên nhân | Cách sửa |
|---|---|---|
| Tick checklist công việc không có tác dụng | `scopeBindings()` đoán sai tên quan hệ | Bỏ scopeBindings, kiểm tra `task_id` trong controller |
| Thông báo có thể trỏ sai địa chỉ khi đổi giữa `localhost/orangecrm` và `127.0.0.1:8000` | Lưu URL tuyệt đối | Lưu đường dẫn tương đối, chống open-redirect |
| Laravel không chạy được dưới `/orangecrm` qua Apache Alias | `.htaccess` mặc định viết cho thư mục gốc | Tự xác định thư mục gốc trong `.htaccess` |
| Đường dẫn PDF đơn hàng chưa khai báo | Thiếu route | Bổ sung `orders.pdf` |
| MySQL XAMPP tự tắt giữa phiên làm việc (phát hiện ngày 13/10) | Tiến trình bị dừng từ bên ngoài, log không ghi lỗi | `START_CRM.bat` tự phát hiện và khởi động lại MySQL |

## 5. Hạn chế đã biết

- Chưa kiểm thử bằng trình duyệt tự động (không có công cụ trong môi trường). Giao diện responsive mới được kiểm tra qua mã CSS, chưa chạy thử trên điện thoại thật.
- Chưa kiểm thử hiệu năng với dữ liệu lớn.
- Email chưa cấu hình (`MAIL_MAILER=log`): liên kết quên mật khẩu được ghi vào log.
