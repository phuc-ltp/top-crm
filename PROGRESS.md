# OrangeCRM / TOP CRM — Tiến độ (file dùng để tiếp tục khi bị dừng)

## Quyết định kỹ thuật
- Laravel **12.x** (thay 10.x vì Laravel 10 hết hỗ trợ bảo mật 04/02/2025). PHP 8.2.12 XAMPP, MariaDB 10.4.32 (root, mật khẩu trống).
- Thư mục: C:\phuc-crm (ngoài htdocs). Apache: C:\xampp\apache\conf\extra\httpd-orangecrm.conf (Alias /orangecrm -> public, Require local).
- Vite `base: './'` (bắt buộc để font/icon chạy dưới /orangecrm).
- DB: `php artisan crm:install` (migrations + seed cấu hình). SQL: database/sql/orangecrm_db.sql (build_sql.php), đã test import 14/10 (423 cột, 0 khác biệt).
- Admin: /setup (chưa có Super Admin) hoặc `php artisan crm:create-admin`. Demo: is_demo; `crm:purge-demo`.
- Test: `php artisan test` → DB orangecrm_test. 77 test PASS (14/10/2026).
- Quyền Claude: .claude/settings.local.json cho phép PowerShell/Edit/Write/Read (user yêu cầu không hỏi lại).

## Trạng thái
- Phase 1–12: XONG (13–14/10/2026).
- 14/10 tối: Trang truy cập theo thiết kế TOP CRM XONG
  - layouts/auth + auth.css (máy tính, cố định 1 màn hình, co theo chiều cao 900/800/700/620) + auth-mobile.css (điện thoại, cho phép cuộn).
  - Đăng nhập bằng email hoặc username; Google/Facebook (Socialite, chỉ tài khoản đã cấp); trang Điều khoản/Bảo mật/Hỗ trợ;
    Cài đặt → tab "Trang đăng nhập & thương hiệu" (secret mã hóa); logo TOP SVG (x-brand-logo, id gradient duy nhất); sidebar dùng thương hiệu TOP.
  - Đã chụp màn hình (Edge headless, scratchpad/shot.ps1) ở 1920x1080, 1672x941, 1600x900, 1440x860, 1366x768, 1280x650, 390/360 mobile — OK.
- docs/MOBILE_UI.md: nơi ghép code giao diện điện thoại (user sẽ gửi code sau).

## Sự cố MySQL (đã xử lý 14/10)
- APPCRASH 0x80000003 (13/10 16:29, 14/10 11:35) của mysqld 10.4.32 — nguyên nhân chưa rõ; đã có watchdog (scripts/mysql-watchdog.ps1, bật từ START_CRM.bat).
- Sau đó MySQL tự tắt vì multi-master.info bị ghi log nhầm (do khởi động mysqld ẩn không có --log-error). Đã chuyển tệp rác sang C:\xampp\mysql\data_junk_backup_20261014, dữ liệu nguyên vẹn. Mọi chỗ khởi động mysqld nay có --log-error.
- my.ini innodb_buffer_pool_size 16M→256M (backup my.ini.bak-orangecrm). Backup DB: storage/backups/.

## MASTER PROMPT TOP CRM ENTERPRISE (nhận 14/10/2026)
- Phase 0 (audit) XONG: docs/PHASE0_AUDIT.md (chỉ đọc, không sửa code/DB). Baseline 77 test pass.
- USER ĐÃ DUYỆT (14/10): (1) multi-tenant shared DB + organization_id + spatie teams; (2) 15 vai trò + ánh xạ; (3) nâng cấp MariaDB 10.11 LTS ở bước 1.0; (5) đăng ký công khai TẮT mặc định (có công tắc). Mặc định: (4) cài Git (user tự cài), (6) giữ tên kỹ thuật orangecrm.
- BƯỚC TIẾP: Phase 1 bước 1.0 (backup → user cài Git → git init + commit mốc → nâng cấp MariaDB 10.11 → chạy 77 test).
- Sau phê duyệt: Phase 1 theo bảng mục 7 trong PHASE0_AUDIT.md (bước 1.0 → 1.10), từng bước có test.

## ĐANG LÀM (tạm dừng 14/10 tối): Design System + Dashboard theo ảnh concept (images/2.png của user)
- ĐÃ VIẾT (chưa build/test xong lần cuối): resources/css/app.css mới (tokens light/dark, palette #211610/#35231B/#D9823B/#F7F3EE, lưới 8px, radius 10–14), layouts/app.blade.php mới (sidebar nhóm thu gọn, topbar: Ctrl+K, theme toggle, Tạo mới, user), app/Support/Menu.php (nhóm + flatRoutes()), app.js (theme toggle + chart theme từ CSS var + Ctrl+K), DashboardService (previousRange, change, opportunityOutcomes, topOpportunities, topSellers, myTasks), DashboardController, dashboard.blade.php mới, components/trend.blade.php, design-system.blade.php + route 'design-system', Setting brand_color mặc định #D9823B.
- PageSmokeTest đã sửa dùng Menu::flatRoutes + thêm route settings.branding, design-system (kiểm tra lại file có đúng không).
- BƯỚC TIẾP: npm run build → php artisan test → sửa lỗi → chụp màn hình bằng Edge headless (cần đăng nhập: dùng DB preview + port 8002 + CDP/Runtime.evaluate để login) ở light/dark, 1920/1366/mobile → báo user duyệt thiết kế.
- Sau khi user duyệt thiết kế: quay lại Phase 1 bước 1.0 (backup, Git, MariaDB 10.11).

## Việc tiếp theo (chờ user)
- User sẽ gửi code/thiết kế giao diện điện thoại → ghép vào resources/css/auth-mobile.css theo docs/MOBILE_UI.md.
- Người dùng tự tạo Super Admin tại http://localhost/orangecrm/setup.
- Cân nhắc nâng cấp MariaDB (10.4 hết hỗ trợ) — cần hỏi user trước.
