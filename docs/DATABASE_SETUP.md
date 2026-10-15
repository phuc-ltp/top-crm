# Hướng dẫn Database

- **Tên database:** `orangecrm_db`
- **Hệ quản trị:** MySQL / MariaDB (trong XAMPP)
- **Kiểu lưu trữ:** InnoDB, bảng mã `utf8mb4` (hiển thị đúng tiếng Việt)
- **Quy mô:** 50 bảng, 491 cột, 97 khóa ngoại (chi tiết và sơ đồ quan hệ: [DATABASE.md](DATABASE.md))

## 1. Hai cách khởi tạo database (chỉ dùng MỘT trong hai)

### Cách chính: lệnh Laravel (khuyên dùng)

```
php artisan crm:install
```

Lệnh này tự tạo database nếu chưa có, tạo các bảng còn thiếu và nạp dữ liệu cấu hình (vai trò, quyền, phòng ban, giai đoạn pipeline...). Chạy lại bao nhiêu lần cũng được, **không bao giờ xóa dữ liệu**. `START_CRM.bat` gọi lệnh này mỗi lần khởi động.

### Cách phụ: import file SQL

Tệp: `database/sql/orangecrm_db.sql`. Tệp gồm cấu trúc bảng, dữ liệu cấu hình và **dữ liệu demo** (các bản ghi demo có `is_demo = 1`).

**Bằng phpMyAdmin:**
1. Mở `http://localhost/phpmyadmin`
2. Bấm tab **Import**
3. Chọn tệp `orangecrm_db.sql`, để bảng mã `utf-8`
4. Bấm **Import** (tệp tự tạo database `orangecrm_db`)

**Bằng dòng lệnh:**
```
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < C:\phuc-crm\database\sql\orangecrm_db.sql
```

> ⚠️ **Chỉ import vào máy chưa có database `orangecrm_db`.** Tệp không chứa lệnh xóa (DROP) nên không làm mất dữ liệu cũ. Tuy vậy, import đè lên database đang có dữ liệu sẽ báo lỗi trùng.

Tệp SQL đã kèm bảng `migrations`, nên sau khi import, Laravel biết cấu trúc đã đủ và sẽ không tạo bảng trùng.

**Đã kiểm thử (17/10/2026):** import vào database trống riêng thành công, import lần hai không lỗi, không còn migration nào thiếu; tệp không chứa tài khoản quản trị, dữ liệu thật, khóa API hay lịch sử đăng nhập. Tạo lại tệp sau khi đổi cấu trúc: `powershell -ExecutionPolicy Bypass -File scripts\generate-sql.ps1` (chạy trên database tạm, không đụng database thật).

### Sau khi import: tạo tài khoản quản trị

Tệp SQL **không có** tài khoản quản trị và **không có** mật khẩu thật. Chọn một trong hai cách:
- Mở trình duyệt vào `http://127.0.0.1:8000/setup` và tự đặt mật khẩu, **hoặc**
- Chạy `php artisan crm:create-admin` rồi nhập email, mật khẩu (mật khẩu được ẩn khi gõ).

## 2. Dữ liệu demo

- Gồm 30 khách hàng, 30 lead, 15 cơ hội, 20 công việc, 10 sản phẩm, 5 chiến dịch, 10 ticket, báo giá/đơn hàng/hóa đơn và 7 nhân viên demo.
- Mọi bản ghi demo có `is_demo = 1`. Khi còn dữ liệu demo, đầu mọi trang hiện dải vàng cảnh báo.
- Tài khoản demo có mật khẩu ngẫu nhiên nên **không đăng nhập được**. Muốn dùng thử, quản trị viên đặt mật khẩu cho họ trong mục *Người dùng*.
- **Xóa toàn bộ dữ liệu demo** trước khi dùng thật:

```
php artisan crm:purge-demo
```

  Lệnh này chỉ xóa bản ghi có `is_demo = 1`, dữ liệu bạn tự nhập vẫn giữ nguyên.

- Dữ liệu demo chỉ được nạp khi `.env` có `CRM_DEMO_DATA=true` và **không bao giờ** nạp khi `APP_ENV=production`.

## 3. Sao lưu (backup)

**Cách 1: phpMyAdmin:** chọn `orangecrm_db`, vào tab **Export**, bấm **Go**. Lưu tệp `.sql` ra USB hoặc Google Drive.

**Cách 2: dòng lệnh** (nên làm hằng tuần):
```
C:\xampp\mysql\bin\mysqldump.exe -u root --default-character-set=utf8mb4 --single-transaction orangecrm_db > D:\backup\orangecrm_%date:~-4%%date:~3,2%%date:~0,2%.sql
```

Nhớ sao lưu thêm thư mục `C:\phuc-crm\storage\app` (ảnh đại diện, logo, tệp đính kèm công việc).

## 4. Khôi phục (restore)

1. Tắt CRM: chạy `STOP_CRM.bat`
2. Trong phpMyAdmin, tạo database trống `orangecrm_db` (Collation: `utf8mb4_unicode_ci`)
3. Chọn database đó, vào tab **Import**, chọn tệp backup, bấm **Go**
4. Chép lại thư mục `storage\app` nếu có
5. Chạy `START_CRM.bat`

> Nếu database `orangecrm_db` đang có dữ liệu, hãy đổi tên database cũ (hoặc sao lưu nó) trước khi khôi phục, tránh mất dữ liệu.

## 5. Các bảng chính

| Nhóm | Bảng |
|---|---|
| Tổ chức | organizations, organization_user |
| Người dùng & quyền | users, departments, roles, permissions, model_has_roles, role_has_permissions, sessions |
| Bảo mật | login_events, personal_access_tokens, password_reset_tokens |
| Khách hàng | customers, customer_tag, tags, contacts, interactions |
| Lead & Marketing | leads, lead_sources, campaigns, campaign_leads |
| Bán hàng | pipeline_stages, opportunities, opportunity_stage_histories, products, product_categories, quotations, quotation_items, sales_orders, sales_order_items, invoices, invoice_items, payments, sales_targets |
| Vận hành | tasks, task_checklist_items, task_comments, attachments, support_tickets, ticket_replies |
| Hệ thống | notifications, activity_logs, settings, migrations, cache, jobs |
