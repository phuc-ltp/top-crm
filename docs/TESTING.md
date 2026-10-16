# Kiểm thử TOP CRM

*Cách chạy, phạm vi bao phủ và quy trình kiểm thử. Kết quả lần chạy gần nhất ở cuối trang. Báo cáo kiểm thử bàn giao ban đầu (14/10): [TEST_REPORT.md](TEST_REPORT.md).*

## 1. Chạy kiểm thử
```powershell
cd C:\phuc-crm
php artisan test                                   # toàn bộ (~7 phút)
php artisan test tests/Feature/ApiTokenTest.php    # một tệp
php artisan test --filter=two_factor               # theo tên
```
- Chạy trên database **riêng** `orangecrm_test` (khai báo trong `phpunit.xml`), làm mới sau mỗi test (`RefreshDatabase`). `TestCase` **từ chối chạy** nếu cấu hình trỏ vào `orangecrm_db`.
- Mỗi test tạo lại 14 vai trò + dữ liệu cấu hình → chậm hơn bình thường nhưng sát thực tế.
- **Không chạy hai bộ test cùng lúc** (dùng chung database test).
- Giao diện phải được build (`npm run build`) trước khi chạy test trang.

## 2. Phạm vi bao phủ (271 test)

| Tệp | Số test | Nội dung chính |
|---|---:|---|
| Unit/BusinessLogicTest | 7 | Tính tiền chứng từ, chuẩn hóa điện thoại, chấm điểm lead, định dạng tiền/ngày, sinh mã |
| AuthTest | 15 | /setup một lần, đăng nhập/đăng xuất, khóa tạm khi sai nhiều lần, quên/đặt lại/đổi mật khẩu, mật khẩu không vào nhật ký |
| LoginPageTest | 12 | Trang đăng nhập (email hoặc tên đăng nhập), Google/Facebook, trang công khai, cài đặt thương hiệu (secret mã hóa), trang lỗi, dashboard |
| AccountSecurityTest | 20 | Quy định mật khẩu, 2FA (luồng, chống dùng lại, giới hạn, mã khôi phục, mã hóa), đặt lại 2FA theo quyền, lịch sử đăng nhập, cảnh báo thiết bị mới, xác minh email, bắt buộc 2FA |
| AuthorizationTest | 9 | Phân quyền hành động và phạm vi "chỉ của mình" |
| RbacTest | 12 | Quản trị nền tảng, 14 vai trò theo tổ chức, bảo vệ chủ sở hữu |
| OrganizationTest | 8 | Tổ chức mặc định, tự gán `organization_id` (không gán được từ form), unique theo tổ chức, thành viên mới, chặn xóa cứng tổ chức có dữ liệu |
| TenantIsolationTest | 15 | Người tổ chức A không xem/sửa/xóa/tìm/xuất/tham chiếu dữ liệu tổ chức B |
| WorkspaceTest | 17 | Chuyển tổ chức, quản lý tổ chức, thành viên dùng chung, tạm ngưng/gỡ |
| ApiTokenTest | 14 | Tạo/thu hồi khóa, phạm vi, không vượt quyền, gắn tổ chức, tự vô hiệu, không dùng cho trang web |
| CustomerTest | 7 | CRUD, kiểm tra dữ liệu, cảnh báo trùng, tìm kiếm/phân trang, nhập CSV, ghi chú tương tác, chống XSS |
| LeadPipelineTest | 6 | Lead, chuyển đổi, pipeline |
| SalesFlowTest | 6 | Báo giá → đơn hàng → hóa đơn → thanh toán, chặn thanh toán vượt |
| OperationsTest | 8 | Công việc (checklist, bình luận, đính kèm, chặn tải tệp của người khác), nhắc việc/quá hạn, chiến dịch, ticket, người dùng, cài đặt, thông báo |
| DatabaseTest | 10 | Bảng InnoDB/utf8mb4, khóa ngoại (RESTRICT/CASCADE/SET NULL), unique, quan hệ, seeder, xóa dữ liệu demo, rollback migration |
| PageSmokeTest | 6 | Mọi trang mở được với mọi vai trò, báo cáo/xuất Excel/PDF, AJAX & API trả JSON |
| LayoutTest | 7 | Sáng/tối theo tài khoản, ô tìm chức năng |
| LocaleTest | 11 | Song ngữ: mọi câu giao diện có bản dịch tiếng Anh, câu dùng trong JavaScript đã khai báo |
| OrganizationSettingsTest | 9 | *(Phase 2)* Cài đặt & phòng ban theo tổ chức, không lẫn giữa tổ chức |
| ActivityTest | 11 | *(Phase 2)* Hoạt động / lịch hẹn, nhắc việc, dòng thời gian qua quyền |
| DetailPageVisibilityTest | 2 | *(Phase 2)* Trang chi tiết không lộ bản ghi liên quan ngoài quyền |
| CustomerProfileTest | 9 | *(Phase 2)* Trường hồ sơ mới, trường tùy biến, gộp khách hàng, nhập Excel, tệp đính kèm |
| LeadManagementTest | 10 | *(Phase 2)* Giai đoạn lead theo tổ chức, lý do thất bại, phát hiện trùng, form website, phân tích |
| PipelineTest | 12 | *(Phase 2)* Nhiều pipeline, sản phẩm trong cơ hội, kéo thả đúng pipeline, dự báo |
| GlobalSearchTest | 10 | *(Phase 2)* Tìm kiếm 11 loại qua quyền & tổ chức, điện thoại mọi cách viết, tìm gần đây riêng tư |
| ExecutiveMetricsTest | 7 | *(Phase 2)* 20 chỉ số điều hành trên dữ liệu mẫu cố định, phạm vi quyền, trạng thái trống, bộ lọc kỳ |
| TrashTest | 5 | *(Phase 2)* Thùng rác: khôi phục theo quyền xóa, phạm vi xem, tổ chức; nhật ký "Khôi phục" |
| ImportExportTest | 6 | *(Phase 2)* Nhập lead / người liên hệ / sản phẩm (kiểm tra từng dòng, trùng, quyền, tổ chức), xuất công việc |

## 3. Kiểm thử ngoài PHPUnit (thực hiện mỗi bước Phase 1 và 2)
| Hạng mục | Cách làm |
|---|---|
| Migration | Bản sao database thật `orangecrm_migcheck`: 3 vòng migrate/rollback + so sánh schema, rồi xóa bản sao |
| Giao diện | Edge headless qua DevTools Protocol, database xem thử `orangecrm_preview` (chỉ dữ liệu demo): chụp màn hình sáng/tối, đo tràn ngang ở 1920 / 1366 / 820 / 390 px |
| Luồng thật | Bật 2FA qua giao diện → QR → nhập mã → mã khôi phục; gọi API bằng HTTP thật (200/401/403) |
| File SQL | `scripts/verify-sql.ps1`: nạp **bản sao đã đổi tên database** vào `orangecrm_sqlcheck` (2 lần), kiểm tra không có quản trị / dữ liệu thật, không còn migration thiếu. **Không bao giờ** nạp thẳng `database/sql/orangecrm_db.sql` để thử — file có lệnh `USE orangecrm_db` (sự cố 16/10/2026) |
| Lỗ hổng thư viện | `composer audit`, `npm audit` |

## 4. Quy tắc khi thêm tính năng
- Mỗi tính năng mới có test cho: người có quyền làm được, người không có quyền bị chặn, dữ liệu tổ chức khác không chạm được.
- Câu giao diện mới phải có trong `lang/en.json` (test `LocaleTest` sẽ báo thiếu).
- Không đánh dấu hoàn thành khi chưa chạy toàn bộ `php artisan test`.

## 5. Kết quả gần nhất
| Hạng mục | Kết quả (16/10/2026, hết Phase 2 — v2.0.0) |
|---|---|
| PHPUnit | **271/271 đạt** (7.891 kiểm tra), 408 giây |
| `composer audit` / `npm audit` | 0 lỗ hổng |
| `scripts/verify-sql.ps1` (nạp bản sao vào database tạm, 2 lần) | Đạt — 57 bảng, không thiếu migration, không có quản trị / dữ liệu thật |
| Môi trường | Windows 11 · XAMPP · PHP 8.2.12 · MariaDB 10.11.19 · Laravel 12.69.3 · Node 24 |
