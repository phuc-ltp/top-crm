# TOP CRM — Phase 0: Đánh giá dự án hiện tại & Kế hoạch Phase 1

> Ngày kiểm tra: 14/10/2026 · Người thực hiện: Claude Code · Phạm vi: **chỉ đọc**, không sửa mã nguồn hay database.

---

## 1. Môi trường & phiên bản (đã kiểm tra thực tế)

| Thành phần | Phiên bản | Đánh giá |
|---|---|---|
| PHP | 8.2.12 (XAMPP) | ✅ Phù hợp Laravel 12 |
| Laravel | **12.69.3** | ✅ Còn hỗ trợ bảo mật. Giữ nguyên, không đổi framework |
| MariaDB | 10.4.32 (XAMPP) | ⚠️ **Hết hỗ trợ từ 06/2024**, đã sập 2 lần (APPCRASH 0x80000003) |
| Composer | 2.10.3 | ✅ |
| Node.js / npm | 24.14 / 11.9 | ✅ |
| Git | **Chưa cài** | ❌ Cần cho GitHub/portfolio |
| Redis | Không có | ℹ️ Dùng queue/cache dạng `database` (đã cấu hình) |
| Web server | Apache XAMPP (`/orangecrm` → `public/`, `Require local`) + `php artisan serve` :8000 | ✅ |

**Thư viện Composer (trực tiếp):** spatie/laravel-permission 6.25, barryvdh/laravel-dompdf 3.1, maatwebsite/excel 3.1, laravel/socialite 5.31, phpunit 11.5. `composer audit`: 0 lỗ hổng.
**Frontend:** Bootstrap 5.3, Bootstrap Icons 1.11, Chart.js 4, SortableJS, Vite 6 (`base: './'`).

## 2. Hiện trạng dữ liệu

- Database `orangecrm_db`: **46 bảng, 69 khóa ngoại**, InnoDB + utf8mb4.
- **Toàn bộ dữ liệu là DEMO** (`is_demo = 1`): 30 khách hàng, 30 lead, 15 cơ hội, 12 báo giá, 9 đơn hàng, 8 hóa đơn, 6 thanh toán, 20 công việc, 10 ticket, 7 tài khoản nhân viên demo.
- **Chưa có tài khoản Super Admin**, chưa có dữ liệu thật → trang `/setup` vẫn mở. Đây là thời điểm **an toàn nhất** để thay đổi cấu trúc (thêm tổ chức, phân quyền mới) vì không có dữ liệu kinh doanh thật bị ảnh hưởng.
- Bản sao lưu gần nhất: `storage/backups/orangecrm_db_20261014_1138.sql`.

## 3. Cấu trúc mã nguồn hiện tại

| Hạng mục | Số lượng | Ghi chú |
|---|---|---|
| Routes | 191 | web + `/api/v1` (xác thực bằng session) |
| Models | 31 | Traits: `HasOwnership`, `HasCode`, `LogsActivity`, `IsSalesDocument` |
| Controllers | 36 | Mỏng, gọi Service; `SalesDocumentController` dùng chung cho báo giá/đơn/hóa đơn |
| Services | 12 | Dashboard, Report, SalesDocument, Payment, Lead, Pipeline, Customer, Import, Export, Upload, TaskReminder, ActivityLogger |
| Policies | 13 | `CrmPolicy` chung (quyền hành động + phạm vi sở hữu) |
| Blade views | 85 | Layout `app` + `auth`, 13 component |
| Migrations | 8 | |
| Tests | 10 file, **77 test, 1.058 assertion — tất cả đạt** | DB test riêng `orangecrm_test` |
| Dòng code PHP (app/) | ~5.900 | |

## 4. Đối chiếu module: Đã có / Cần sửa / Còn thiếu

Ký hiệu: ✅ có và hoạt động · 🟡 có nhưng cần nâng cấp theo Master Prompt · ❌ chưa có

| # | Module | Trạng thái | Cần làm thêm |
|---|---|---|---|
| — | Xác thực (đăng nhập, quên/đặt lại MK, đổi MK, hồ sơ, phiên, remember, rate-limit, khóa TK, Google/Facebook) | 🟡 | Email verification, **2FA + recovery codes**, lịch sử đăng nhập, thông báo bảo mật, Microsoft OAuth, đăng ký (nếu bật SaaS), **sửa lỗi tranh chấp `/setup`** |
| — | Đa tổ chức (multi-tenant) | ❌ | Toàn bộ: organizations, memberships, chuyển tổ chức, phạm vi dữ liệu |
| — | RBAC | 🟡 | 7 vai trò → 15 vai trò; tách quyền nền tảng / tổ chức; phân quyền theo tổ chức |
| — | Giao diện (sidebar, topbar, footer) | 🟡 | Workspace selector, menu nhóm thu gọn, tìm menu, **theme sáng/tối**, chọn ngôn ngữ, breadcrumb đã có |
| — | Đa ngôn ngữ vi/en | ❌ | 985 dòng tiếng Việt viết cứng trong 85 view, 0 lần dùng `__()` |
| 01 | Dashboard | 🟡 | Có 8 KPI + 6 biểu đồ. Thiếu: won/lost, giá trị TB, retention, forecast, ticket, theo sản phẩm, so sánh kỳ, bộ lọc Yesterday/7 ngày |
| 02 | Khách hàng | 🟡 | Có CRUD, trùng lặp, import/export, tags, gán, timeline. Thiếu: country, segment, lifecycle, consent, custom fields, **merge**, đính kèm |
| 03 | Lead | 🟡 | Có scoring, chuyển đổi, gán, campaign. Thiếu: stage cấu hình được, lý do thất bại, web form, nhắc follow-up, phát hiện trùng |
| 04 | Pipeline & Deals | 🟡 | Có Kanban kéo thả + lịch sử. Thiếu: **nhiều pipeline**, sản phẩm trong deal, đính kèm, forecast. (Bảng tên `opportunities`) |
| 05 | Sản phẩm & dịch vụ | 🟡 | Có SKU, danh mục, giá, ảnh. Thiếu: loại product/service, giá nhập, thuế, biến thể, bảng giá, nhà cung cấp |
| 06 | Báo giá & Đơn hàng | 🟡 | Có tính tiền backend, PDF, chuyển đổi, duyệt đơn. Thiếu: mẫu báo giá, duyệt báo giá, trả hàng |
| 07 | Hóa đơn & Thanh toán | 🟡 | Có thanh toán từng phần, công nợ, PDF. Thiếu: hoàn tiền, credit note, nhắc thanh toán, cấu hình thuế |
| 08 | Kho hàng | ❌ | Toàn bộ |
| 09 | Dự án & Công việc | 🟡 | Có task, checklist, comment, đính kèm, lịch, nhắc quá hạn. Thiếu: **projects**, subtask, dependency, Kanban task, workload |
| 10 | Nhân viên & Đội nhóm | 🟡 | Có user, phòng ban, chỉ tiêu. Thiếu: teams, chi nhánh, cấp quản lý, KPI cá nhân/đội |
| 11 | Hỗ trợ (ticket) | 🟡 | Có workflow trạng thái, ghi chú nội bộ. Thiếu: danh mục, **SLA**, escalation, khảo sát hài lòng |
| 12 | Marketing | 🟡 | Có chiến dịch, gắn lead, ROI cơ bản. Thiếu: email template, lịch gửi, consent/unsubscribe, GetResponse |
| 13 | Communication center | ❌ | Toàn bộ (Zalo OA, SMTP… chỉ làm khi có thông tin tích hợp) |
| 14 | Lịch & cuộc hẹn | ❌ | Có lịch công việc; thiếu lịch hẹn/cuộc họp |
| 15 | Tài liệu | ❌ | Có bảng `attachments` (chỉ cho task); thiếu quản lý tài liệu, phiên bản |
| 16 | Workflow automation | ❌ | Toàn bộ |
| 17 | Báo cáo | 🟡 | Có 8 báo cáo + CSV/XLSX/PDF. Thiếu: lưu bộ lọc, báo cáo định kỳ, so sánh kỳ |
| 18 | AI assistant | ❌ | Tùy chọn, làm ở Phase 6 |
| — | Global search | 🟡 | Có (6 loại). Thiếu: phím tắt, gợi ý gần đây, thêm sản phẩm/đơn/task/nhân viên |
| — | Thông báo | 🟡 | Có in-app + đánh dấu đọc. Thiếu: email, tùy chọn cá nhân |
| — | Audit log | 🟡 | Có (`activity_logs`). Thiếu: `organization_id`, retention |
| — | REST API | 🟡 | Session-based. Thiếu: **Sanctum token**, tài liệu OpenAPI |
| — | SaaS subscription | ❌ | Phase 6 |

## 5. Rà soát bảo mật & nợ kỹ thuật

| Mức | Vấn đề | Đề xuất |
|---|---|---|
| 🔴 Cao | `/setup`: kiểm tra "chưa có Super Admin" **nằm ngoài khóa** → 2 request đồng thời có thể tạo 2 Super Admin | Dùng khóa nguyên tử (`Cache::lock` / bản ghi `app_initialized` có unique) + kiểm tra lại trong transaction |
| 🔴 Cao | MariaDB 10.4.32 hết hỗ trợ, đã sập 2 lần | Nâng cấp MariaDB 10.11 LTS (cần bạn đồng ý; có sao lưu trước) |
| 🟠 Trung bình | Chưa có Git → không có lịch sử thay đổi, khó hoàn tác | Cài Git for Windows, khởi tạo repo, commit mốc hiện tại |
| 🟠 Trung bình | Không có 2FA, chưa xác minh email | Phase 1 |
| 🟠 Trung bình | API dùng session, chưa có token | Thêm `laravel/sanctum` (chính chủ Laravel) |
| 🟡 Thấp | `SESSION_ENCRYPT=false` | Bật `true` |
| 🟡 Thấp | Chuỗi giao diện viết cứng tiếng Việt | Chuyển dần sang `lang/vi.json` + `lang/en.json` |
| 🟡 Thấp | Tên kỹ thuật cũ "orangecrm" (thư mục, DB, URL) | Giữ nguyên để tránh rủi ro; thêm alias `/topcrm` (tùy chọn) |
| ℹ️ | Lead status / pipeline cố định trong code | Chuyển thành cấu hình theo tổ chức (Phase 2) |
| ✅ | CSRF, XSS escape, chống SQL injection, bcrypt, rate-limit đăng nhập, khóa TK, upload kiểm tra MIME, file riêng tư, security headers, chống CSV injection, tiền dạng `DECIMAL(15,2)`, tính tiền ở backend | Giữ nguyên |

## 6. Kiến trúc đề xuất

### 6.1 Multi-tenant
**Shared database + cột `organization_id`** trên mọi bảng nghiệp vụ.

```
organizations ──< organization_user >── users
      │                (role theo tổ chức, status, joined_at)
      ├──< branches, departments, teams
      ├──< organization_settings (thay bảng settings toàn cục)
      └──< customers, leads, opportunities, products, quotations, ... (organization_id)
```

- Trait `BelongsToOrganization`: tự gán `organization_id` khi tạo, kèm **global scope** chỉ lấy bản ghi của tổ chức đang làm việc.
- Middleware `SetCurrentOrganization`: xác định tổ chức từ session, kiểm tra người dùng có là thành viên không.
- Unique constraint chuyển thành **theo tổ chức**: `(organization_id, code)`, `(organization_id, sku)`, `(organization_id, number)`.
- Job/queue mang theo `organization_id`. Export/báo cáo/tìm kiếm đều qua scope.
- spatie/laravel-permission bật **Teams mode** (team = organization) để vai trò gắn theo từng tổ chức.
- **Di chuyển dữ liệu không phá hủy:** tạo tổ chức mặc định "TOP – Technology of Prowess" (id=1), gán toàn bộ bản ghi hiện có vào tổ chức này.

### 6.2 Phân quyền (RBAC) hai tầng

| Tầng | Vai trò | Ánh xạ từ vai trò cũ |
|---|---|---|
| **Nền tảng** (ngoài tổ chức) | Platform Super Administrator | Super Admin |
| **Tổ chức** | Organization Owner | (mới, người tạo tổ chức) |
| | Organization Administrator | Administrator |
| | Sales Manager / Sales Employee | Sales Manager / Sales Staff |
| | Marketing Manager / Marketing Employee | Marketing Staff → Marketing Employee |
| | Customer Support Manager / Support Agent | Customer Support → Support Agent |
| | Finance Manager / Accountant | (mới) |
| | Inventory Manager / Project Manager / Employee | (mới) |
| | Read-only Auditor | Viewer |

Quyền vẫn dạng `{module}.{action}`, gồm view, view_all, create, update, delete, export, approve, assign, manage_settings, manage_users. Kiểm tra quyền luôn ở server qua Policy kèm scope tổ chức.

### 6.3 Cấu trúc thư mục (mở rộng, không phá cấu trúc hiện tại)

```
app/
  Actions/                 ← nghiệp vụ một bước có thể tái dùng (ConvertLead, MergeCustomers…)
  Http/Middleware/SetCurrentOrganization.php
  Models/Concerns/BelongsToOrganization.php
  Support/Tenancy/CurrentOrganization.php   ← nắm "tổ chức đang làm việc"
  Services/…               (giữ nguyên)
lang/
  vi.json, en.json         ← chuỗi giao diện
  vi/*.php, en/*.php       ← validation, auth…
docs/                      ← ARCHITECTURE, DATABASE, API, SECURITY, TESTING, CHANGELOG, ROADMAP
```

Giữ mô hình MVC + Service hiện tại. Chỉ thêm `Actions/` khi nghiệp vụ đủ phức tạp, không trừu tượng hóa thừa.

## 7. Kế hoạch Phase 1 — Foundation (thứ tự thực hiện)

Mỗi bước: kiểm tra → lập kế hoạch → làm → migrate an toàn → **chạy test** → báo cáo. Không sang bước sau khi test đỏ.

| Bước | Nội dung | Tiêu chí nghiệm thu |
|---|---|---|
| **1.0** | Chuẩn bị an toàn: sao lưu DB, cài Git, commit mốc, (tùy chọn) nâng cấp MariaDB 10.11 | Có backup + commit đầu tiên; 77 test vẫn đạt |
| **1.1** | **Bảo mật `/setup`**: khóa nguyên tử chống tạo 2 admin, cờ `app_initialized`, bật `SESSION_ENCRYPT` | Test mô phỏng 2 request đồng thời → chỉ 1 admin |
| **1.2** | **Đa ngôn ngữ nền tảng**: `lang/vi.json`, `lang/en.json`, middleware `SetLocale`, menu chọn ngôn ngữ hoạt động thật; chuyển layout, menu, trang đăng nhập, xác thực sang `__()` | Đổi vi ⇄ en trên trang đăng nhập + layout; test |
| **1.3** | **Organizations** (migration không phá hủy): bảng `organizations`, `organization_user`; tổ chức mặc định; thêm `organization_id` vào các bảng nghiệp vụ + backfill = 1; unique theo tổ chức | Migrate xong, dữ liệu demo còn nguyên, 77 test đạt |
| **1.4** | **Tenant scoping**: trait `BelongsToOrganization`, `CurrentOrganization`, middleware, cập nhật Lookup/Search/Report/Export/Dashboard/Jobs | **Test cô lập**: user tổ chức A không thấy/sửa/xuất/tìm được dữ liệu tổ chức B (mọi module) |
| **1.5** | **RBAC 2 tầng**: spatie teams mode, 15 vai trò, ánh xạ vai trò cũ, quyền nền tảng tách riêng, giao diện ma trận quyền theo tổ chức | Test quyền theo vai trò × tổ chức |
| **1.6** | **Chuyển tổ chức & quản lý thành viên**: workspace selector, mời/thêm thành viên, đổi vai trò trong tổ chức; Platform admin quản lý danh sách tổ chức | Test chuyển tổ chức, thành viên không thuộc tổ chức bị chặn |
| **1.7** | **Layout nâng cấp**: menu nhóm thu gọn, tìm menu, **theme sáng/tối** (lưu theo người dùng), topbar có workspace + ngôn ngữ, footer theo cài đặt | Chụp màn hình 1920/1366/tablet/mobile, không tràn ngang |
| **1.8** | **Bảo mật tài khoản**: xác minh email, **2FA (TOTP) + recovery codes**, lịch sử đăng nhập, cảnh báo đăng nhập mới | Test bật/tắt 2FA, đăng nhập cần mã, mã khôi phục dùng 1 lần |
| **1.9** | **API token**: `laravel/sanctum`, API v1 theo tổ chức + quyền | Test API với token, sai tổ chức → 403/404 |
| **1.10** | Tài liệu: ARCHITECTURE, DATABASE (ERD), SECURITY, TESTING, CHANGELOG; cập nhật file SQL | Tài liệu khớp với mã thực tế |

**Thư viện dự kiến thêm** (đều tương thích Laravel 12 / PHP 8.2, sẽ kiểm tra trước khi cài): `laravel/sanctum` (chính chủ), `pragmarx/google2fa` + `bacon/bacon-qr-code` (2FA). Không thêm React/Vue.

## 8. Quyết định (đã phê duyệt 14/10/2026)

> ✅ 1. Shared DB + organization_id + spatie Teams · ✅ 2. 15 vai trò · ✅ 3. Nâng cấp MariaDB 10.11 LTS ở bước 1.0 · ✅ 5. Đăng ký công khai tắt mặc định · Mặc định: 4. cài Git, 6. giữ tên kỹ thuật "orangecrm".

Danh sách gốc:

1. **Kiến trúc đa tổ chức**: shared database + `organization_id` + spatie Teams (đề xuất).
2. **Bộ 15 vai trò & ánh xạ** từ 7 vai trò cũ như bảng 6.2.
3. **Nâng cấp MariaDB 10.4 → 10.11 LTS**: thay chương trình MySQL của XAMPP, giữ dữ liệu, có sao lưu trước.
4. **Cài Git for Windows**: cần bạn tự cài vì cần quyền quản trị Windows. Tôi hướng dẫn từng bước.
5. **Đăng ký công khai** (người lạ tự tạo tổ chức mới): đề xuất **tắt mặc định**, chỉ bật khi chuẩn bị bán SaaS.
6. **Giữ tên kỹ thuật "orangecrm"** (thư mục, DB, URL) để tránh rủi ro; giao diện đã là TOP CRM.
