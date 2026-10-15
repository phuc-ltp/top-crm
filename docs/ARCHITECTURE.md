# Kiến trúc kỹ thuật OrangeCRM

*Dành cho lập trình viên tiếp quản hoặc bảo trì hệ thống.*

## 1. Công nghệ

| Lớp | Công nghệ |
|---|---|
| Backend | PHP 8.2, **Laravel 12.69**, Eloquent ORM, Form Request, Policy, Middleware |
| Phân quyền | spatie/laravel-permission 6.25 (role + permission, cache) |
| PDF | barryvdh/laravel-dompdf 3.1 (font DejaVu Sans cho tiếng Việt) |
| Excel/CSV | maatwebsite/excel 3.1 |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons, Chart.js 4, SortableJS, Vite 6 |
| Database | MariaDB 10.11 LTS (XAMPP, nâng cấp 15/10/2026; tương thích MySQL 8), InnoDB, utf8mb4_unicode_ci |

### Về phiên bản Laravel

Yêu cầu ban đầu là Laravel 10.x. Laravel 10 đã **ngừng nhận bản vá bảo mật từ 04/02/2025**, nên một CRM chứa dữ liệu khách hàng không nên chạy trên phiên bản đó. Laravel 12 giữ nguyên MVC, Blade, Eloquent, Form Request và Policy (cú pháp gần như giống hệt), chỉ khác cấu trúc bootstrap gọn hơn (`bootstrap/app.php` thay cho `Http/Kernel.php`). `composer audit` tại thời điểm bàn giao: **0 lỗ hổng**.

## 2. Cấu trúc thư mục

```
app/
  Exports/ArrayExport.php          Xuất CSV (BOM UTF-8) / XLSX chung
  Http/Controllers/                Mỗi module một controller, mỏng, gọi Service
    SalesDocumentController.php    Lớp cha dùng chung cho Báo giá/Đơn hàng/Hóa đơn
    Api/                           REST API JSON (/api/v1)
    Auth/                          Đăng nhập, quên mật khẩu, /setup
  Http/Middleware/                 EnsureUserIsActive, RedirectToSetup, SecurityHeaders
  Http/Requests/                   CustomerRequest, LeadRequest
  Models/                          31 model Eloquent
    Concerns/HasOwnership.php      scope visibleTo(): giới hạn dữ liệu theo quyền
    Concerns/HasCode.php           Sinh mã KH00001, LD00001, BG00001...
    Concerns/LogsActivity.php      Tự ghi activity_logs (lọc trường nhạy cảm)
    Concerns/IsSalesDocument.php   Thuộc tính chung chứng từ bán hàng
  Notifications/CrmNotification.php Thông báo nội bộ (kênh database)
  Policies/CrmPolicy.php           Policy chung: quyền hành động + phạm vi sở hữu
  Services/                        Nghiệp vụ: SalesDocument, Payment, Lead, Pipeline,
                                   Customer, CustomerImport, Dashboard, Report,
                                   Export, Upload, TaskReminder, ActivityLogger
  Support/                         PermissionCatalog, Menu, Lookup
  helpers.php                      money(), vn_date(), badge(), label_of()
database/
  migrations/                      Toàn bộ schema
  seeders/                         RolePermission, ReferenceData, DemoData
  sql/orangecrm_db.sql             File SQL tương đương migration + dữ liệu
resources/views/                   layouts/, components/, partials/, theo module, pdf/
resources/css/app.css              Design tokens (CSS variables) màu cam
resources/js/app.js                Toast, AJAX helper, Kanban, Chart, form, tìm kiếm
routes/web.php, api.php, console.php
tests/Feature, tests/Unit          68 test
```

## 3. Các quyết định thiết kế chính

### Phân quyền 2 lớp
1. **Quyền hành động:** `{module}.{action}`, gồm 81 quyền. Danh mục và ma trận mặc định nằm ở `App\Support\PermissionCatalog`.
2. **Phạm vi dữ liệu:** người có `{module}.view_all` thấy tất cả. Người không có chỉ thấy bản ghi mình là `assigned_to` hoặc `created_by` (trait `HasOwnership::scopeVisibleTo`, kiểm tra trong `CrmPolicy::allows`).
- `Gate::before` cho Super Admin toàn quyền.
- Mọi controller gọi `$this->authorize()`. Danh sách luôn qua `visibleTo()`. Form không cho người thiếu quyền `assign` đổi người phụ trách (xử lý ở backend).

### Tiền tệ tính ở backend
`SalesDocumentService::calculate()` tính lại thành tiền, tạm tính, chiết khấu (≤ tạm tính), thuế, tổng. Mọi giá trị tổng gửi từ trình duyệt đều bị bỏ qua. Lưu chứng từ và các dòng trong **một transaction**.

### Thanh toán
`PaymentService` khóa dòng hóa đơn (`lockForUpdate`), chặn thanh toán vượt số còn phải thu, rồi tính lại `amount_paid` và `payment_status` từ bảng `payments`.

### Định nghĩa chỉ số
- **Doanh thu** = tổng `payments.amount` theo `payment_date` (ghi nhận theo dòng tiền), chỉ tính hóa đơn `issued`.
- **Pipeline** = tổng `value` của cơ hội ở giai đoạn chưa Thắng/Thất bại. **Giá trị có trọng số** = value × probability.
- **Tỷ lệ chuyển đổi lead** = lead tạo trong kỳ có status `converted` / tổng lead tạo trong kỳ.

### Mã nghiệp vụ
Sinh **sau khi insert** theo id (`HasCode`) nên không trùng khi nhiều người tạo cùng lúc. Cột có ràng buộc unique.

### Xóa
Xóa mềm (`deleted_at`) cho dữ liệu nghiệp vụ. Khóa ngoại `RESTRICT` cho khách hàng có chứng từ, `SET NULL` cho người phụ trách, `CASCADE` cho dòng chi tiết.

### Nhật ký
`LogsActivity` ghi created/updated (kèm giá trị cũ và mới)/deleted. `ActivityLogger::scrub()` loại bỏ password, token, secret. Lỗi khi ghi log không làm hỏng nghiệp vụ.

### Thông báo
Chỉ dùng kênh `database` (không gửi email hàng loạt). Lưu đường dẫn tương đối nên chạy đúng ở cả `localhost/orangecrm` và `127.0.0.1:8000`. Có chống open-redirect.

### Bảo mật
CSRF (mặc định Laravel), escape Blade chống XSS, Eloquent/binding chống SQL injection, cột sắp xếp theo whitelist, `LIKE` được escape, mật khẩu bcrypt, giới hạn 5 lần đăng nhập sai trong 5 phút, throttle các route nhạy cảm, khóa tài khoản sẽ xóa phiên ngay, upload kiểm tra MIME, tên tệp ngẫu nhiên, tệp đính kèm lưu ngoài `public` và tải qua controller có kiểm tra quyền. Header bảo mật X-Frame-Options, nosniff, Referrer-Policy. Chống formula injection khi xuất CSV. Apache chỉ trỏ vào `public/`, `Require local`.

## 4. Luồng nghiệp vụ

```
Lead ──(chuyển đổi)──► Customer + Opportunity ──(kéo thả)──► Won
                                    │
                         Quotation ─┴─(tạo đơn)─► SalesOrder ─(duyệt)─► Invoice (nháp)
                                                                  │ (phát hành)
                                                                  ▼
                                                       Payments ─► payment_status
```

## 5. REST API

Tiền tố `/api/v1`, xác thực bằng phiên đăng nhập web + CSRF, giới hạn 120 yêu cầu/phút. Phản hồi dạng `{success, message, data, meta}`.

| Method | Endpoint |
|---|---|
| GET | `/api/v1/stats?period=this_month` |
| GET/POST | `/api/v1/customers` |
| GET/PUT/DELETE | `/api/v1/customers/{id}` |
| GET/POST | `/api/v1/leads` |
| GET/PUT | `/api/v1/leads/{id}` |
| GET | `/api/v1/opportunities`, `/api/v1/opportunities/{id}` |

Muốn dùng API từ ứng dụng bên ngoài, cần bổ sung `laravel/sanctum` để xác thực bằng token.

## 6. Hiệu năng

- Chỉ mục trên các cột lọc/sắp xếp (status, assigned_to, created_at, email, phone, các khóa ngoại).
- Eager loading (`with`, `withCount`, `withSum`) ở mọi danh sách. Phân trang phía máy chủ (10–100 dòng mỗi trang).
- Dashboard dùng truy vấn tổng hợp (`SUM`/`COUNT ... GROUP BY`). Biểu đồ tải bằng AJAX.
- Cài đặt hệ thống được cache. Quyền được spatie cache.
- Asset được bundle và tối ưu bằng Vite.
- Chưa đo hiệu năng dưới tải lớn, nên không đưa ra cam kết số liệu.

## 7. Lệnh Artisan riêng

`crm:install`, `crm:health`, `crm:create-admin`, `crm:purge-demo`, `crm:task-reminders` (scheduler chạy mỗi 15 phút qua `schedule:work`, được `START_CRM.bat` khởi động).

## 8. Kiểm thử

```
php artisan test
```
Test chạy trên database **riêng** `orangecrm_test` (cấu hình trong `phpunit.xml`). `TestCase` từ chối chạy nếu trỏ vào `orangecrm_db`.
