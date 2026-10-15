# OrangeCRM / TOP CRM — Tiến độ (file dùng để tiếp tục khi bị dừng)

## Quyết định kỹ thuật
- Laravel **12.x** (thay 10.x vì Laravel 10 hết hỗ trợ bảo mật 04/02/2025). PHP 8.2.12 XAMPP, MariaDB 10.11.19 (từ 15/10; root, mật khẩu trống, chỉ nghe 127.0.0.1).
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

## Giao diện macOS kính mờ — XONG 15/10/2026 (commit de5b831), CHỜ USER DUYỆT
- Layout kính mờ (wallpaper, sidebar kính + đèn macOS, crm-window, topbar, footer), dashboard mới theo ảnh 4 (dữ liệu thật), endpoint dashboard.widget.
- 80 test PASS. Đã chụp sáng/tối 1600/1366/mobile qua CSDL xem thử `orangecrm_preview` (tài khoản preview, chỉ dữ liệu demo; chạy: DB_DATABASE=orangecrm_preview php artisan serve --port=8002 --no-reload).
- Lưu ý: mật khẩu tạm của tài khoản phuphuc không còn đăng nhập được (user đã đổi) → KHÔNG đụng tài khoản thật; chụp màn hình dùng CSDL preview.

## Sao lưu & Git — XONG 15/10/2026
- `SAO_LUU.bat` → scripts/backup.ps1: mysqldump (MYSQL_PWD, không lộ dòng lệnh) → C:\TOPCRM_Backup\database (zip, giữ 30); uploads (robocopy); .env → secrets\ (ACL chỉ user/SYSTEM/Admins, giữ 10); git commit tự động (chặn tệp bí mật) + push sang kho bare C:\TOPCRM_Backup\git\phuc-crm.git; push origin nếu có.
- Đã thử khôi phục bản sao lưu vào CSDL tạm: số bản ghi khớp 100%, đã xóa CSDL tạm. Clone kho sao lưu OK, không có .env.
- Hướng dẫn: docs/GIT_BACKUP.md. GitHub: XONG 15/10 — remote origin = https://github.com/phuc-ltp/top-crm (PRIVATE), đã quét toàn bộ lịch sử không có bí mật; SAO_LUU.bat tự push origin.
- 15/10: USER ĐÃ DUYỆT giao diện macOS → làm Phase 1, yêu cầu làm kỹ từng phần.

## PHASE 1 — Foundation
- [x] 1.0 Nâng cấp MariaDB 10.4.32 → 10.11.19 (15/10): dữ liệu khớp 100%, 80 test pass, bind-address=127.0.0.1. Chi tiết + hoàn tác: docs/MARIADB_UPGRADE.md. Thư mục cũ C:\xampp\mysql_10.4.32_old (giữ ~2 tuần).
- [x] 1.1 Bảo mật /setup (15/10): cờ vĩnh viễn settings.app_initialized_at (App\Support\AppInitialization; migration 2026_10_15_100000 ghi cờ cho hệ thống đã có admin) + Cache::lock + claim() dựa UNIQUE key trong transaction; crm:create-admin cũng ghi cờ. Đã thử song song thật (12 yêu cầu/4 máy chủ → 1 admin) và 2 kết nối CSDL độc lập. SESSION_ENCRYPT=true (.env + .env.example). 86 test pass.
- [x] 1.2 Đa ngôn ngữ vi/en (15/10): toàn bộ giao diện (~1.100 câu trong lang/en.json), bộ chọn ngôn ngữ thật, users.locale, email theo ngôn ngữ người nhận, trang pháp lý tiếng Anh. Hướng dẫn: docs/I18N.md. Chưa dịch: nội dung nhật ký/thông báo đã lưu (dữ liệu). 97 test pass.
- GHI CHÚ cho 1.8: chính sách mật khẩu chưa thống nhất (setup 10 ký tự + hoa/thường/số; đổi/đặt lại chỉ 8 ký tự) → dùng Password::defaults().
- [x] 1.3 Organizations (15/10): bảng organizations/organization_user, users.current_organization_id, organization_id trên 21 bảng (backfill = 1), unique theo tổ chức, trait BelongsToOrganization + CurrentOrganization (tự gán, không fillable). Thử 3 vòng migrate/rollback trên bản sao, cấu trúc sau rollback giống hệt. Đã chạy trên CSDL thật (tổ chức 1 "TOP CRM", owner = phuphuc). Tài liệu: docs/MULTI_TENANCY.md.
- [x] 1.4 Tenant scoping (16/10): OrganizationScope trên 21 model (đóng chặn khi thiếu tổ chức), chặn đổi tổ chức, middleware SetCurrentOrganization, TenantRule::exists/unique/member, User::inOrganization + route binding {user}, nhắc việc theo từng tổ chức. TenantIsolationTest 15 test (A không chạm được dữ liệu B qua mọi đường). 120 test pass. Chi tiết: docs/MULTI_TENANCY.md.
- [x] 1.5 RBAC 2 tầng (16/10): users.is_platform_admin (quản trị nền tảng, Gate::before) + 14 vai trò riêng mỗi tổ chức (spatie teams, team = organization_id, OrganizationTeamResolver), RoleProvisioner, đổi tên vai trò cũ giữ quyền/người dùng (Super Admin→Owner+platform, Administrator→Organization Administrator, Sales Staff→Sales Employee, Marketing Staff→Marketing Employee, Customer Support→Support Agent, Viewer→Read-only Auditor). Đã chạy trên CSDL thật + seeder. 132 test pass. Tài liệu docs/RBAC.md. Ghi chú: test chạy ~210s (tạo 14 vai trò mỗi test).
- [x] 1.6 Chuyển tổ chức/thành viên (16/10): menu chuyển tổ chức, /platform/organizations (tạo tổ chức + chủ sở hữu, tạm ngưng), OrganizationProvisioner (vai trò + dữ liệu mặc định), thêm tài khoản có sẵn bằng email, khóa = tạm ngưng thành viên, xóa = gỡ khỏi tổ chức, chặn chiếm tài khoản xuyên tổ chức, cache demo theo tổ chức. WorkspaceTest 17 test, 149 test pass. Ghi chú: users.department_id còn chung (nên theo membership); cài đặt (tên công ty...) vẫn chung toàn hệ thống.
- [ ] 1.7 Layout  - [ ] 1.8 2FA/xác minh email  - [ ] 1.9 Sanctum API  - [ ] 1.10 Tài liệu

## Sự cố MySQL (đã xử lý 14/10)
- APPCRASH 0x80000003 (13/10 16:29, 14/10 11:35) của mysqld 10.4.32 — nguyên nhân chưa rõ; đã có watchdog (scripts/mysql-watchdog.ps1, bật từ START_CRM.bat).
- Sau đó MySQL tự tắt vì multi-master.info bị ghi log nhầm (do khởi động mysqld ẩn không có --log-error). Đã chuyển tệp rác sang C:\xampp\mysql\data_junk_backup_20261014, dữ liệu nguyên vẹn. Mọi chỗ khởi động mysqld nay có --log-error.
- my.ini innodb_buffer_pool_size 16M→256M (backup my.ini.bak-orangecrm). Backup DB: storage/backups/.

## MASTER PROMPT TOP CRM ENTERPRISE (nhận 14/10/2026)
- Phase 0 (audit) XONG: docs/PHASE0_AUDIT.md (chỉ đọc, không sửa code/DB). Baseline 77 test pass.
- USER ĐÃ DUYỆT (14/10): (1) multi-tenant shared DB + organization_id + spatie teams; (2) 15 vai trò + ánh xạ; (3) nâng cấp MariaDB 10.11 LTS ở bước 1.0; (5) đăng ký công khai TẮT mặc định (có công tắc). Mặc định: (4) cài Git (user tự cài), (6) giữ tên kỹ thuật orangecrm.
- BƯỚC TIẾP: Phase 1 bước 1.0 (backup → user cài Git → git init + commit mốc → nâng cấp MariaDB 10.11 → chạy 77 test).
- Sau phê duyệt: Phase 1 theo bảng mục 7 trong PHASE0_AUDIT.md (bước 1.0 → 1.10), từng bước có test.

## 15/10/2026
- Lỗi "404 đen" user gặp thực chất là 419 (CSRF hết hạn: trang /setup mở > 2 giờ). ĐÃ SỬA: bootstrap/app.php bắt HttpException 419 → quay lại form giữ dữ liệu + cảnh báo; trang lỗi mang thương hiệu resources/views/errors/*.
- ĐÃ TẠO Super Admin: Trịnh Phú Phúc · phuphuc40@gmail.com · username phuphuc (mật khẩu ngẫu nhiên đã báo user trong chat, user cần đổi).
- Design System + Dashboard mới: build OK, 79 test PASS, đã chụp light/dark/1600/1366/mobile qua CDP (scratchpad/cdp.ps1 đăng nhập thật).
- Git: đã khởi tạo repo nhánh main, commit mốc 26c7c2f (user đã cài Git). .gitignore loại .env, storage/backups.
- CÒN của bước 1.0: nâng cấp MariaDB 10.11 (đã duyệt) — CHƯA làm. Chờ user duyệt thiết kế rồi tiếp Phase 1 (1.1 bảo mật /setup → 1.10).

## (cũ) Design System + Dashboard theo ảnh concept (images/2.png của user) — đã xong 15/10
- ĐÃ VIẾT (chưa build/test xong lần cuối): resources/css/app.css mới (tokens light/dark, palette #211610/#35231B/#D9823B/#F7F3EE, lưới 8px, radius 10–14), layouts/app.blade.php mới (sidebar nhóm thu gọn, topbar: Ctrl+K, theme toggle, Tạo mới, user), app/Support/Menu.php (nhóm + flatRoutes()), app.js (theme toggle + chart theme từ CSS var + Ctrl+K), DashboardService (previousRange, change, opportunityOutcomes, topOpportunities, topSellers, myTasks), DashboardController, dashboard.blade.php mới, components/trend.blade.php, design-system.blade.php + route 'design-system', Setting brand_color mặc định #D9823B.
- PageSmokeTest đã sửa dùng Menu::flatRoutes + thêm route settings.branding, design-system (kiểm tra lại file có đúng không).
- BƯỚC TIẾP: npm run build → php artisan test → sửa lỗi → chụp màn hình bằng Edge headless (cần đăng nhập: dùng DB preview + port 8002 + CDP/Runtime.evaluate để login) ở light/dark, 1920/1366/mobile → báo user duyệt thiết kế.
- Sau khi user duyệt thiết kế: quay lại Phase 1 bước 1.0 (backup, Git, MariaDB 10.11).

## Việc tiếp theo (chờ user)
- User sẽ gửi code/thiết kế giao diện điện thoại → ghép vào resources/css/auth-mobile.css theo docs/MOBILE_UI.md.
- Người dùng tự tạo Super Admin tại http://localhost/orangecrm/setup.
- Cân nhắc nâng cấp MariaDB (10.4 hết hỗ trợ) — cần hỏi user trước.
