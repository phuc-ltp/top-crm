# Kiến trúc kỹ thuật TOP CRM

*Dành cho lập trình viên tiếp quản hoặc bảo trì hệ thống. Cập nhật: hết Phase 1 (bước 1.10).*

## 1. Công nghệ

| Lớp | Công nghệ |
|---|---|
| Backend | PHP 8.2, **Laravel 12.69**, Eloquent ORM, Form Request, Policy, Middleware |
| Phân quyền | spatie/laravel-permission 6.25 ở **chế độ teams** (vai trò theo từng tổ chức, `team_foreign_key = organization_id`) |
| API | laravel/sanctum 4.3 (khóa API cá nhân gắn tổ chức) |
| Xác thực 2 lớp | TOTP tự viết theo RFC 6238 (`App\Support\Security\Totp`), mã QR bằng bacon/bacon-qr-code 3.0 |
| PDF | barryvdh/laravel-dompdf 3.1 (font DejaVu Sans cho tiếng Việt) |
| Excel/CSV | maatwebsite/excel 3.1 |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons, Chart.js 4, SortableJS, Vite 6 (giao diện kính kiểu macOS, sáng/tối) |
| Database | MariaDB 10.11 LTS (XAMPP; tương thích MySQL 8), InnoDB, utf8mb4_unicode_ci |
| Ngôn ngữ | Tiếng Việt (mặc định) và tiếng Anh — xem [I18N.md](I18N.md) |

### Về phiên bản Laravel
Yêu cầu ban đầu là Laravel 10.x. Laravel 10 đã **ngừng nhận bản vá bảo mật từ 04/02/2025**, nên dùng Laravel 12: cùng MVC, Blade, Eloquent, Form Request, Policy; chỉ khác cấu trúc bootstrap gọn hơn (`bootstrap/app.php` thay cho `Http/Kernel.php`).

## 2. Cấu trúc thư mục

```
app/
  Exports/ArrayExport.php            Xuất CSV (BOM UTF-8) / XLSX chung
  Http/Controllers/                  Mỗi module một controller, mỏng, gọi Service
    SalesDocumentController.php      Lớp cha dùng chung cho Báo giá/Đơn hàng/Hóa đơn
    Api/                             REST API JSON (/api/v1) — xem API.md
    Auth/                            Đăng nhập, 2FA challenge, quên mật khẩu, /setup, Google/Facebook
    Platform/OrganizationController  Quản trị nền tảng: tạo / tạm ngưng tổ chức
    SecurityController               Trang Bảo mật tài khoản (2FA, lịch sử đăng nhập, xác minh email)
    ApiTokenController               Tạo / thu hồi khóa API
    WorkspaceController              Chuyển tổ chức đang làm việc
  Http/Middleware/
    EnsureUserIsActive               Đẩy người dùng bị khóa ra ngay
    SetCurrentOrganization           Xác định tổ chức đang làm việc (web), kiểm tra tư cách thành viên
    ApiContext                       Như trên cho /api: tổ chức của khóa API, tài khoản & thành viên còn hoạt động
    RequireTwoFactor                 Ép tài khoản quản trị bật 2FA khi cài đặt bắt buộc
    SetLocale, SecurityHeaders, RedirectToSetup
  Models/                            Model Eloquent
    Concerns/BelongsToOrganization   Tự gán organization_id + global scope OrganizationScope (fail-closed)
    Concerns/HasOwnership            scope visibleTo(): giới hạn dữ liệu theo quyền "xem tất cả"
    Concerns/HasCode                 Sinh mã KH00001, LD00001, BG00001...
    Concerns/LogsActivity            Tự ghi activity_logs (bỏ trường ẩn / nhạy cảm)
    ApiToken, LoginEvent, Organization ...
  Notifications/                     CrmNotification (chuông), ResetPassword, VerifyEmail, NewDeviceLogin (email)
  Policies/                          CrmPolicy chung, UserPolicy (bảo vệ chủ sở hữu / quản trị nền tảng)
  Services/                          Nghiệp vụ: SalesDocument, Payment, Lead, Pipeline, Customer, Dashboard,
                                     Report, Export, Upload, TaskReminder, ActivityLogger; Auth/LoginFlow
  Support/
    PermissionCatalog                Danh mục quyền + 14 vai trò tổ chức + ma trận mặc định
    Tenancy/                         CurrentOrganization, TenantRule, OrganizationTeamResolver (spatie teams),
                                     RoleProvisioner, OrganizationProvisioner
    Security/                        PasswordPolicy, Totp, TwoFactor, UserAgent
    AppInitialization, JsTranslations, Menu, Lookup
  helpers.php                        money(), vn_date(), badge(), label_of()
database/
  migrations/                        Toàn bộ schema (xem DATABASE.md)
  seeders/                           RolePermission, ReferenceData, DemoData
  sql/orangecrm_db.sql               File SQL tương đương (tạo bằng scripts/generate-sql.ps1)
lang/                                en.json (khóa = câu tiếng Việt), vi/, en/labels.php
resources/views/                     layouts/, components/, partials/, theo module, pdf/
resources/css/app.css                Design tokens (CSS variables), sáng/tối
resources/js/app.js                  Toast, AJAX, Kanban, Chart, form, tìm kiếm, tìm menu, sáng/tối
routes/web.php, api.php, console.php
scripts/                             backup.ps1 (SAO_LUU.bat), generate-sql.ps1, mysql-watchdog.ps1
tests/Feature, tests/Unit            190 test — xem TESTING.md
```

## 3. Các quyết định thiết kế chính

### Nhiều tổ chức (multi-tenancy) — chi tiết: [MULTI_TENANCY.md](MULTI_TENANCY.md)
- Một database, cột `organization_id` trên mọi bảng nghiệp vụ. Model dùng trait `BelongsToOrganization`: tự gán tổ chức khi tạo, global scope lọc theo tổ chức, **không có tổ chức → không trả bản ghi nào** (fail-closed), cấm đổi tổ chức của bản ghi.
- Tổ chức đang làm việc: `CurrentOrganization` (scoped singleton). Thứ tự: giá trị đặt rõ (middleware/job) → **tổ chức của khóa API** → tổ chức đang chọn của người dùng → tổ chức mặc định.
- `EnsureUserIsActive`, `SetCurrentOrganization`, `ApiContext` được đặt **trước `SubstituteBindings`** trong danh sách ưu tiên middleware, để bản ghi trên route (`{customer}`...) luôn được tìm trong đúng tổ chức.
- Validation tham chiếu (`exists`, `unique`) dùng `TenantRule` để không trỏ sang dữ liệu tổ chức khác.

### Phân quyền 3 lớp — chi tiết: [RBAC.md](RBAC.md)
1. **Quản trị nền tảng** (`users.is_platform_admin`): toàn quyền mọi tổ chức qua `Gate::before`.
2. **Quyền hành động theo tổ chức:** `{module}.{action}` (gồm `api.use`); 14 vai trò mỗi tổ chức, chủ sở hữu tự chỉnh ma trận.
3. **Phạm vi dữ liệu:** có `{module}.view_all` thấy tất cả; không có chỉ thấy bản ghi mình phụ trách/tạo (`HasOwnership::scopeVisibleTo`, `CrmPolicy`).
- Mọi controller gọi `$this->authorize()`; danh sách luôn qua `visibleTo()`; người thiếu quyền `assign` không đổi được người phụ trách (xử lý ở backend).

### Bảo mật tài khoản & API
Xem [SECURITY.md](SECURITY.md) (tổng hợp), [ACCOUNT_SECURITY.md](ACCOUNT_SECURITY.md) (2FA, lịch sử đăng nhập), [API.md](API.md) (khóa API).

### Tiền tệ tính ở backend
`SalesDocumentService::calculate()` tính lại thành tiền, tạm tính, chiết khấu (≤ tạm tính), thuế, tổng. Mọi giá trị tổng gửi từ trình duyệt đều bị bỏ qua. Lưu chứng từ và các dòng trong **một transaction**.

### Thanh toán
`PaymentService` khóa dòng hóa đơn (`lockForUpdate`), chặn thanh toán vượt số còn phải thu, rồi tính lại `amount_paid` và `payment_status` từ bảng `payments`.

### Định nghĩa chỉ số
- **Doanh thu** = tổng `payments.amount` theo `payment_date` (dòng tiền), chỉ tính hóa đơn `issued`.
- **Pipeline** = tổng `value` của cơ hội chưa Thắng/Thất bại. **Giá trị có trọng số** = value × probability.
- **Tỷ lệ chuyển đổi lead** = lead tạo trong kỳ có status `converted` / tổng lead tạo trong kỳ.

### Mã nghiệp vụ
Sinh **sau khi insert** theo id (`HasCode`), cột unique theo tổ chức. *Hạn chế đã biết:* mã dựa trên id toàn hệ thống nên mỗi tổ chức thấy mã không liên tục.

### Xóa
Xóa mềm (`deleted_at`) cho dữ liệu nghiệp vụ và người dùng. Khóa ngoại `RESTRICT` cho khách hàng có chứng từ, `SET NULL` cho người phụ trách, `CASCADE` cho dòng chi tiết.

### Nhật ký & thông báo
`LogsActivity` ghi created/updated (giá trị cũ/mới)/deleted, bỏ các trường ẩn (mật khẩu, mã 2FA, khóa API). Thông báo nội bộ qua kênh `database`; email chỉ dùng cho đặt lại mật khẩu, xác minh email, cảnh báo đăng nhập mới.

## 4. Luồng nghiệp vụ

```
Lead ──(chuyển đổi)──► Customer + Opportunity ──(kéo thả)──► Won
                                    │
                         Quotation ─┴─(tạo đơn)─► SalesOrder ─(duyệt)─► Invoice (nháp)
                                                                  │ (phát hành)
                                                                  ▼
                                                       Payments ─► payment_status
```

### Luồng đăng nhập
```
Mật khẩu / Google / Facebook đúng ─► tài khoản hoạt động? ─► bật 2FA? ─ có ─► /two-factor-challenge (5 phút, 5 lần)
                                                                  └ không ─┐            │ mã đúng
                                                                           ▼            ▼
                                          LoginFlow::complete: đăng nhập, ghi login_events, cảnh báo thiết bị mới
```

## 5. REST API
Tiền tố `/api/v1`, xác thực bằng khóa API (`Authorization: Bearer topcrm_...`) hoặc phiên web cùng tên miền. Mỗi khóa gắn một tổ chức; quyền thực tế = quyền người tạo ∩ phạm vi khóa; 120 yêu cầu/phút mỗi khóa. Chi tiết: [API.md](API.md).

## 6. Hiệu năng
- Chỉ mục trên cột lọc/sắp xếp (status, assigned_to, created_at, email, phone, organization_id, khóa ngoại).
- Eager loading ở mọi danh sách; phân trang phía máy chủ (10–100 dòng).
- Dashboard dùng truy vấn tổng hợp; biểu đồ tải bằng AJAX. Cài đặt và quyền được cache.
- Chưa đo hiệu năng dưới tải lớn, nên không đưa ra cam kết số liệu.

## 7. Lệnh Artisan riêng
`crm:install`, `crm:health`, `crm:create-admin`, `crm:purge-demo`, `crm:task-reminders` (mỗi 15 phút), `crm:ticket-sla` (mỗi 10 phút), `crm:sales-automation` (mỗi giờ), `crm:prune-notifications` (02:30), `crm:prune-attendance-selfies` (03:00), `crm:asset-reminders` (08:00), `model:prune` cho `LoginEvent` (hằng ngày) — scheduler chạy qua `schedule:work`, được `START_CRM.bat` khởi động.

## 8. Kiểm thử
`php artisan test` — chạy trên database riêng `orangecrm_test` (cấu hình `phpunit.xml`); `TestCase` từ chối chạy nếu trỏ vào `orangecrm_db`. Chi tiết: [TESTING.md](TESTING.md).
