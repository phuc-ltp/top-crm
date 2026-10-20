# Cấu trúc database TOP CRM

*Dành cho lập trình viên. Cài đặt, import file SQL, sao lưu/khôi phục: xem [DATABASE_SETUP.md](DATABASE_SETUP.md).*

- Database: `orangecrm_db` · MariaDB 10.11 (tương thích MySQL 8) · InnoDB · `utf8mb4_unicode_ci`.
- **50 bảng**, 97 khóa ngoại. Nguồn sự thật là `database/migrations/`; file `database/sql/orangecrm_db.sql` được tạo lại bằng `scripts/generate-sql.ps1`.
- Database phụ: `orangecrm_test` (chỉ cho `php artisan test`), `orangecrm_preview` (dữ liệu demo để chụp giao diện). Không chứa dữ liệu thật.

## 1. Quy ước chung

| Quy ước | Ý nghĩa |
|---|---|
| `organization_id` | Bản ghi thuộc một tổ chức. Model dùng `BelongsToOrganization` → tự gán + lọc theo tổ chức (fail-closed). Unique nghiệp vụ (mã KH, SKU, tên thẻ...) là **theo tổ chức**. |
| `deleted_at` | Xóa mềm (khách hàng, lead, cơ hội, chứng từ, sản phẩm, công việc, ticket, chiến dịch, người dùng, tổ chức). |
| `is_demo` | `1` = dữ liệu demo; xóa toàn bộ bằng `php artisan crm:purge-demo`. Không bao giờ tính là doanh thu thật. |
| `assigned_to`, `created_by` | Người phụ trách / người tạo → phạm vi dữ liệu cho người không có quyền "xem tất cả". `SET NULL` khi xóa người dùng. |
| `code` | Mã nghiệp vụ (KH00001, LD00001, BG00001, DH00001, HD00001, TK00001...) sinh sau khi insert. |
| Tiền | `DECIMAL(15,2)`, luôn tính lại ở backend. |

## 2. Sơ đồ quan hệ (ERD)

### 2.1 Tổ chức, người dùng, phân quyền, bảo mật
```mermaid
erDiagram
    organizations ||--o{ organization_user : "thành viên"
    users ||--o{ organization_user : ""
    users }o--o| organizations : "current_organization_id"
    organizations }o--o| users : "owner_id"
    organizations ||--o{ roles : "vai trò riêng"
    roles ||--o{ role_has_permissions : ""
    permissions ||--o{ role_has_permissions : ""
    roles ||--o{ model_has_roles : "gán cho user (theo tổ chức)"
    users }o--o| departments : "department_id"
    users ||--o{ login_events : "lịch sử đăng nhập"
    users ||--o{ personal_access_tokens : "khóa API (tokenable)"
    organizations ||--o{ personal_access_tokens : ""
    organizations ||--o{ activity_logs : ""
    users ||--o{ activity_logs : ""
```

### 2.2 Bán hàng
```mermaid
erDiagram
    lead_sources ||--o{ leads : ""
    lead_sources ||--o{ customers : ""
    leads }o--o| customers : "converted_customer_id"
    leads }o--o| opportunities : "converted_opportunity_id"
    customers ||--o{ contacts : ""
    customers ||--o{ customer_tag : ""
    tags ||--o{ customer_tag : ""
    customers ||--o{ opportunities : ""
    contacts |o--o{ opportunities : ""
    pipelines ||--o{ pipeline_stages : ""
    pipelines ||--o{ opportunities : ""
    pipeline_stages ||--o{ opportunities : ""
    opportunities ||--o{ opportunity_items : ""
    lost_reasons |o--o{ opportunities : ""
    opportunities ||--o{ opportunity_stage_histories : ""
    opportunities |o--o{ quotations : ""
    customers ||--o{ quotations : ""
    quotations ||--o{ quotation_items : ""
    quotations |o--o{ sales_orders : ""
    customers ||--o{ sales_orders : ""
    sales_orders ||--o{ sales_order_items : ""
    sales_orders |o--o{ invoices : ""
    customers ||--o{ invoices : ""
    invoices ||--o{ invoice_items : ""
    invoices ||--o{ payments : ""
    product_categories ||--o{ products : ""
    suppliers ||--o{ products : ""
    price_lists ||--o{ price_list_items : ""
    price_lists ||--o{ customers : ""
    warehouses ||--o{ stock_levels : ""
    products ||--o{ stock_levels : ""
    stock_documents ||--o{ stock_document_items : ""
    stock_documents ||--o{ stock_movements : ""
    products |o--o{ quotation_items : ""
    products |o--o{ sales_order_items : ""
    products |o--o{ invoice_items : ""
```

### 2.3 Vận hành
```mermaid
erDiagram
    users ||--o{ tasks : "assigned_to"
    tasks ||--o{ task_checklist_items : ""
    tasks ||--o{ task_comments : ""
    customers ||--o{ support_tickets : ""
    contacts |o--o{ support_tickets : ""
    support_tickets ||--o{ ticket_replies : ""
    campaigns ||--o{ campaign_leads : ""
    leads ||--o{ campaign_leads : ""
    users ||--o{ sales_targets : ""
```
`interactions` và `attachments` là quan hệ đa hình (`*_type`, `*_id`) gắn vào khách hàng, lead, cơ hội, công việc... (tên ngắn trong `Relation::morphMap`).

## 3. Danh sách bảng

### Tổ chức & tài khoản
| Bảng | Mô tả |
|---|---|
| `organizations` | Doanh nghiệp dùng hệ thống (tên, slug, trạng thái active/suspended, chủ sở hữu). `id = 1` là tổ chức mặc định. |
| `organization_user` | Tư cách thành viên: `status` active / invited / suspended, `joined_at`. |
| `users` | Tài khoản (dùng chung giữa các tổ chức): email, username, mật khẩu bcrypt, `is_platform_admin`, `locale`, `theme`, `notification_preferences` (JSON: tùy chọn nhận thông báo theo loại, kênh app/mail), `current_organization_id`, trạng thái, cột 2FA (`two_factor_secret` & `two_factor_recovery_codes` mã hóa bằng APP_KEY, `two_factor_confirmed_at`, `two_factor_last_step`), `email_verified_at`. |
| `departments` | Phòng ban theo tổ chức. Phòng ban của người dùng lưu ở `organization_user.department_id` (từ bước 2.1; `users.department_id` cũ không còn dùng). |
| `organization_settings` | Cài đặt riêng của từng tổ chức (khóa–giá trị, unique theo tổ chức). |
| `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, `model_has_permissions` | spatie/laravel-permission chế độ teams: `roles.organization_id`, `model_has_roles.organization_id`. |
| `login_events` | Lịch sử đăng nhập (sự kiện, phương thức, IP, user-agent, băm cookie thiết bị, thiết bị mới). Tự xóa sau 180 ngày. |
| `personal_access_tokens` | Khóa API Sanctum: chỉ lưu băm SHA-256, `abilities`, `organization_id`, `expires_at`, `last_used_at`, `last_used_ip`. |
| `password_reset_tokens`, `sessions` | Mặc định Laravel (phiên lưu trong DB để xem/đăng xuất thiết bị). |

### Bán hàng
| Bảng | Mô tả |
|---|---|
| `lead_sources` | Nguồn khách (Website, Giới thiệu...). |
| `leads` | Khách tiềm năng: điểm, giai đoạn (`status` = mã trong `lead_stages`, chuỗi), giá trị dự kiến, liên kết khi chuyển đổi; từ bước 2.4: `qualified_at`, `lost_reason_id` + `lost_note`, đồng ý marketing (`marketing_consent`, `_at`, `_source`). |
| `lead_stages` | Giai đoạn lead của từng tổ chức (`key`, tên, màu, thứ tự, `is_system` cho converted/lost, `is_qualified`). |
| `lost_reasons` | Lý do thất bại của từng tổ chức (`applies_to`: lead / opportunity / both). |
| `customers` | Khách hàng (cá nhân/doanh nghiệp), người phụ trách, nguồn, thẻ; từ bước 2.3: `country` (ISO 2 ký tự), `segment`, `lifecycle_stage` + `lifecycle_changed_at`, `preferred_channel`, `marketing_consent` + `_at` + `_source`, `custom_fields` (JSON), `merged_into_id` (bản đã gộp). |
| `custom_field_definitions` | Trường tùy biến của từng tổ chức (`entity`, `key` duy nhất theo tổ chức, `type`, `options`, bắt buộc/ẩn, thứ tự). |
| `contacts` | Người liên hệ của khách hàng doanh nghiệp. |
| `tags`, `customer_tag` | Thẻ phân loại khách hàng. |
| `pipelines` | Pipeline bán hàng của từng tổ chức (từ bước 2.5); đúng một pipeline `is_default`, có thể ẩn (`is_active`). |
| `pipeline_stages` | Giai đoạn của một pipeline (`pipeline_id`, `slug` duy nhất trong pipeline, thứ tự, xác suất, màu, Thắng/Thất bại). |
| `opportunities`, `opportunity_stage_histories` | Cơ hội bán hàng và lịch sử chuyển giai đoạn. `pipeline_id` luôn khớp pipeline của giai đoạn (gán tự động khi lưu); `lost_reason_id` (lý do có cấu trúc) + `lost_reason` (ghi chú). |
| `opportunity_items` | Sản phẩm / dịch vụ dự kiến trong cơ hội (SL, đơn giá, chiết khấu %, thành tiền tính ở máy chủ). |
| `quotations`, `quotation_items` | Báo giá. |
| `sales_orders`, `sales_order_items` | Đơn hàng (duyệt bởi `approved_by`). |
| `invoices`, `invoice_items` | Hóa đơn (nháp → phát hành), `amount_paid`, `payment_status`. |
| `payments` | Thanh toán; nguồn duy nhất để tính doanh thu. |
| `product_categories`, `products` | Danh mục và sản phẩm/dịch vụ. Từ bước 3.1: `type` (product / service — dịch vụ không theo dõi tồn kho), `purchase_price` (giá vốn, chỉ người sửa sản phẩm thấy), `supplier_id`. |
| `price_lists`, `price_list_items` | Bảng giá theo nhóm khách hàng (bước 3.1b): giá riêng từng sản phẩm, bật/tắt; `customers.price_list_id` (SET NULL khi xóa bảng giá). Giá được điền sẵn khi lập chứng từ và dùng khi dòng để trống đơn giá. Biến thể sản phẩm: `products.parent_id` + `variant_name` (1 cấp). |
| `warehouses` | Kho theo tổ chức (bước 3.2): mã, tên, kho mặc định, đang dùng. Kho có phát sinh không xóa được. |
| `stock_levels` | Tồn kho theo kho × sản phẩm: `quantity` (tồn thực), `reserved` (đang giữ cho đơn — bước 3.3). Chỉ InventoryService thay đổi, trong giao dịch có khóa dòng. |
| `stock_documents`, `stock_document_items` | Phiếu kho đã ghi sổ: nhập PN (nhà cung cấp, đơn giá nhập), xuất PX, điều chỉnh kiểm kê DC (`counted_quantity`, `quantity` = chênh lệch), chuyển kho CK. Bất biến — không sửa / xóa. |
| `stock_movements` | Sổ kho bất biến: số lượng có dấu, `balance_after`, đơn giá. Tổng phát sinh = tồn kho. `products.average_cost` = giá vốn bình quân gia quyền (cập nhật khi nhập), `products.reorder_level` = mức tồn tối thiểu (cảnh báo). |
| `stock_reservations` | Hàng đang giữ cho đơn hàng (bước 3.3), theo đơn × sản phẩm × kho; tổng nằm ở `stock_levels.reserved`. `sales_orders.warehouse_id` = kho xuất (trống = đơn không theo dõi kho, gồm mọi đơn tạo trước bước 3.3 hoặc khi tắt Cài đặt › Kho hàng › "Đơn hàng giữ hàng & xuất kho tự động"). `stock_documents.sales_order_id` = phiếu xuất giao hàng / phiếu nhập hàng trả lại (NT) của đơn. |
| `quotation_templates` | Mẫu báo giá theo tổ chức (bước 3.4): dòng sản phẩm (JSON), thuế, điều khoản; tạo từ một báo giá. `quotations.approval_status` (pending / approved / rejected), `approved_by`, `approved_at`, `approval_note`: duyệt chiết khấu vượt ngưỡng `quotation_discount_threshold` (cài đặt tổ chức). |
| `credit_notes` | Phiếu giảm trừ GT (bước 3.5), bất biến, giảm số còn phải thu: `invoices.credited_amount`. Hoàn tiền = dòng `payments` loại `refund` với số tiền âm (doanh thu theo dòng tiền tự trừ). `payments.reconciled_at/by` = đối soát. `invoices.due_reminder_sent_at`, `overdue_notified_at` = mốc đã nhắc. Còn phải thu = tổng − giảm trừ − đã thu. |
| `suppliers` | Nhà cung cấp theo tổ chức (bước 3.1): mã tự sinh NCC00001, liên hệ, mã số thuế, trạng thái; xóa mềm (khôi phục ở Thùng rác). |
| `sales_targets` | Chỉ tiêu doanh số theo nhân viên và kỳ. |

### Vận hành & hệ thống
| Bảng | Mô tả |
|---|---|
| `tasks`, `task_checklist_items`, `task_comments` | Công việc, checklist, bình luận. Từ bước 4.1, `tasks` có thêm `project_id` (dự án), `parent_id` (việc con, một cấp), `start_date`, `estimated_hours`. |
| `projects` | Dự án (bước 4.1): mã tự sinh DA00001, khách hàng (tùy chọn), người quản lý, trạng thái, mức ưu tiên, thời gian, ngân sách tham khảo; xóa mềm. |
| `branches` | Chi nhánh / văn phòng (bước 4.2), duy nhất theo tên trong tổ chức. |
| `teams`, `team_members` | Nhóm làm việc (trưởng nhóm, phòng ban tùy chọn) và thành viên. |
| `employee_profiles` | Hồ sơ nhân viên 1–1 với thành viên tổ chức: mã NV, chức danh, quản lý trực tiếp (chặn vòng lặp), chi nhánh, ngày vào làm, trạng thái. Trường nhạy cảm (`date_of_birth`, `personal_phone`, `personal_email`, `home_address`, `id_number`, `emergency_contact`, `hr_notes`) mã hóa bằng APP_KEY, ẩn khỏi JSON và nhật ký. |
| `ticket_categories` | Danh mục ticket hỗ trợ (bước 4.3), duy nhất theo tên trong tổ chức. |
| `ticket_status_histories` | Lịch sử đổi trạng thái ticket (chỉ thêm, không sửa). |
| `support_tickets` (bổ sung 4.3) | `category_id`, hạn SLA `first_response_due_at` / `resolution_due_at`, `first_responded_at`, tạm dừng khi chờ khách `paused_at` / `paused_minutes`, leo thang `escalation_level` (0/1/2) / `escalated_at`, hài lòng `satisfaction_rating` (1–5) / `satisfaction_comment`. Ticket cũ để trống SLA. |
| `project_members` | Thành viên dự án (duy nhất theo dự án + người dùng). Thành viên xem được mọi công việc của dự án. |
| `task_dependencies` | Phụ thuộc "việc `depends_on_id` phải xong trước việc `task_id`"; chặn vòng lặp ở `TaskService`. |
| `support_tickets`, `ticket_replies` | Hỗ trợ khách hàng. |
| `campaigns`, `campaign_leads` | Chiến dịch marketing. |
| `interactions` | **Hoạt động** (ghi chú, gọi, email, gặp, việc theo dõi) — đa hình (khách hàng, người liên hệ, lead, cơ hội). `status` planned/done, `scheduled_at`, `assigned_to`, `outcome`, `completed_at`, `reminded_at` (từ bước 2.2). |
| `attachments` | Tệp đính kèm (lưu ngoài `public/`, tải qua controller có kiểm tra quyền) — đa hình. |
| `activity_logs` | Nhật ký hoạt động theo tổ chức (không ghi trường nhạy cảm). |
| `notifications` | Thông báo trong chuông (kênh database). |
| `search_histories` | Tìm kiếm gần đây (bước 2.6): riêng từng người dùng trong từng tổ chức, duy nhất theo (tổ chức, người dùng, từ khóa), chỉ giữ 10 từ khóa mới nhất; chỉ ghi khi mở trang kết quả đầy đủ. Người dùng tự xóa được. |
| `settings` | Cài đặt chung toàn hệ thống (trang đăng nhập, Google/Facebook, trang pháp lý, cờ khởi tạo, bắt buộc 2FA). Secret OAuth lưu mã hóa. |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` | Hạ tầng Laravel. |

## 4. Quy trình thay đổi cấu trúc (bắt buộc)
1. Viết migration **chỉ thêm** khi có thể; `down()` phải đảo ngược hoàn toàn.
2. Thử trên **bản sao** database thật (`orangecrm_migcheck`): 3 vòng migrate/rollback + so sánh schema, rồi xóa bản sao.
3. Chạy `php artisan test`.
4. Sao lưu (`SAO_LUU.bat`) → migrate database thật → merge vào `main` ngay.
5. Tạo lại file SQL: `powershell -ExecutionPolicy Bypass -File scripts\generate-sql.ps1`.

**Không bao giờ** dùng `php artisan ... --env=testing` (không có `.env.testing` nên sẽ trỏ vào database thật); muốn thao tác database khác hãy đặt `DB_DATABASE=...` và in `DB::connection()->getDatabaseName()` trước.
