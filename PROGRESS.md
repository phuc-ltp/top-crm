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
- [x] 1.7 Layout (17/10): ô tìm chức năng trong menu (không dấu, Enter mở mục đầu, Esc xóa), giao diện sáng/tối lưu theo tài khoản (users.theme, POST /preferences/theme, HTML vẽ sẵn theme của tài khoản nên không nháy màu trên thiết bị mới), sửa tràn ngang 2px ở 390px. Đã đo tràn ngang 22 trang ở 1920/1366/820/390: không trang nào tràn. LayoutTest 7 test, 156 test pass.
- [x] 1.8 Bảo mật tài khoản (17/10): quy định mật khẩu chung (10–128 ký tự, hoa/thường/số; PasswordPolicy + Password::defaults), xác thực 2 lớp TOTP tự viết theo RFC 6238 (đã kiểm với bộ mẫu RFC) + QR (bacon/bacon-qr-code 3.0) + 8 mã khôi phục băm, chống dùng lại mã, giới hạn 5 lần/5 phút theo tài khoản, áp dụng cả Google/Facebook; quản trị viên tắt 2FA cho nhân viên; tùy chọn bắt buộc 2FA cho quản trị (chỉ quản trị nền tảng đổi, mặc định tắt); lịch sử đăng nhập login_events (không lưu chuỗi gõ nhầm, tự xóa sau 180 ngày); cảnh báo thiết bị mới (chuông + email); xác minh email (không chặn đăng nhập vì chưa có SMTP). Trang /profile/security. AccountSecurityTest 20 test, 176 test pass. Tài liệu docs/ACCOUNT_SECURITY.md.
- [x] 1.9 API bằng khóa (17/10): Laravel Sanctum 4.3.3, khóa gắn tổ chức (organization_id), 6 phạm vi (stats/customers/leads/opportunities read/write), quyền thực tế = quyền người dùng ∩ phạm vi, quyền mới api.use (Chủ sở hữu + Quản trị viên), thời hạn 30–365 ngày (không vĩnh viễn), tối đa 10 khóa/người/tổ chức, tiền tố topcrm_, chỉ lưu băm, khóa hiện 1 lần, thu hồi (quản trị thu hồi được khóa thành viên), tự ngừng khi mất quyền/khóa tài khoản/tạm ngưng/xóa. ApiContext + CurrentOrganization ưu tiên tổ chức của khóa; xác định tổ chức trước khi tìm bản ghi (priority trước SubstituteBindings, áp dụng cả web). Trang /profile/api-tokens. ApiTokenTest 14 test, 190 test pass. Thử thật bằng HTTP: 200/401/403 đúng. docs/API.md.
- [x] 1.10 Tài liệu (17/10): ARCHITECTURE (viết lại), DATABASE (ERD mermaid, 50 bảng/97 FK), SECURITY (tổng hợp + checklist trước khi lên Internet), TESTING (190 test), CHANGELOG 1.0.0; cập nhật README, ADMIN_GUIDE (vai trò mới, tổ chức, bảo mật), USER_GUIDE, DATABASE_SETUP. File SQL tạo lại bằng scripts/generate-sql.ps1 (database tạm orangecrm_sqlgen, chỉ demo; đã import thử 2 lần). composer/npm audit 0 lỗ hổng. Gỡ email khỏi PROGRESS.md.

**PHASE 1 HOÀN TẤT (17/10/2026).** Tiếp theo: Phase 2 — đầu tiên trình bày 2 cách cho bạn bè dùng thử (mạng nội bộ / Internet) để user chọn; tách cài đặt & phòng ban theo tổ chức; cấu hình SMTP.

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
- ĐÃ TẠO Super Admin: Trịnh Phú Phúc · username phuphuc (mật khẩu ngẫu nhiên đã báo user trong chat, user cần đổi).
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

## PHASE 2 — CRM Core (bắt đầu 17/10/2026, kế hoạch: docs/PHASE2_PLAN.md)
- [x] 2.1 Cài đặt & phòng ban theo tổ chức (17/10): organization_settings (14 khóa theo tổ chức, sao chép giá trị cũ → không đổi gì), Setting tự đọc/ghi theo tổ chức, trang thương hiệu/đăng nhập chỉ quản trị nền tảng, múi giờ có tác dụng thật (hiển thị giờ), tab "Chung" đã dịch; phòng ban theo organization_user.department_id (đã chuyển 8/8 người dùng). OrganizationSettingsTest 9 test, 199 test pass. Thử 3 vòng trên bản sao: dữ liệu khớp 100%.
- [x] 2.2 Hoạt động & dòng thời gian (17/10): interactions → hoạt động (ghi nhận / lên lịch, người phụ trách, hoàn thành + kết quả, sửa, nhắc trước 1 giờ qua crm:task-reminders, thông báo khi được giao), loại mới "Việc theo dõi", trang /activities (của tôi / tất cả được xem, lọc, bộ đếm), TimelineService (hoạt động + nhật ký + cơ hội/báo giá/đơn/hóa đơn/thanh toán/ticket, qua quyền). SỬA LỖ HỔNG CŨ: trang khách hàng/cơ hội/lead hiện bản ghi liên quan không kiểm tra quyền → Controller::onlyVisible(). ActivityTest 11 + DetailPageVisibilityTest 2, 212 test pass. Dữ liệu cũ giữ nguyên (checksum khớp sau 3 vòng). User chọn CÁCH B (giữ demo ở TOP CRM, tạo tổ chức mới cho dữ liệu thật — chờ tên công ty).
- [x] 2.3 Khách hàng (16/10): quốc gia, phân khúc, vòng đời (tự ghi thời điểm), kênh liên lạc, đồng ý marketing (bắt buộc nguồn, tự ghi thời điểm, rút lại được); trường tùy biến theo tổ chức (6 kiểu, Cài đặt → Trường tùy biến, ẩn không mất dữ liệu); gộp khách hàng (chuyển 10 loại bản ghi + thẻ, bổ sung chỗ trống, lưu trữ bản trùng, nhật ký); nhập CSV + Excel (bù số 0 điện thoại, cột mới + trường tùy biến); tệp đính kèm khách hàng; lọc nâng cao + sắp xếp; xuất đủ cột; API trả trường mới. CustomerProfileTest 9 test, 221 test pass. Tổ chức ToS (#2) đã tạo, chủ sở hữu = phuphuc.
- [x] 2.4 Leads (16/10): lead_stages theo tổ chức (giữ 6 mã cũ, converted/lost là giai đoạn hệ thống; thêm/đổi tên/đổi màu/sắp xếp/xóa có chuyển lead), Lead::statuses() thay hằng số ở mọi nơi (dashboard, báo cáo, chiến dịch, tìm kiếm), lost_reasons + bắt buộc lý do khi thất bại, qualified_at, hẹn theo dõi (tạo hoạt động) + cột/lọc theo dõi, phát hiện trùng với lead & khách hàng, form thu lead công khai /f/{token} (honeypot, thời gian điền, throttle, đổi token), đồng ý marketing chuyển sang khách hàng khi chuyển đổi, trang /leads/analytics (cohort). Điểm lead cũ giữ nguyên. LeadManagementTest 10 test, 231 test pass.
- [x] 2.5 Nhiều pipeline & deal (16/10): bảng pipelines (mặc định mỗi tổ chức, giữ nguyên 6 giai đoạn cũ), pipeline_id trên giai đoạn & cơ hội (tự gán theo giai đoạn), opportunity_items (tính tiền ở máy chủ), lost_reason_id (bắt buộc khi thất bại, kiểm tra thuộc tổ chức; chữ cũ giữ làm ghi chú), Kanban có tab pipeline + hộp chọn lý do, không kéo sang pipeline khác, hẹn theo dõi + tệp đính kèm cơ hội, trang Cấu hình pipeline (bất biến: ≥1 mở/thắng/thua, xóa giai đoạn chuyển cơ hội), trang Dự báo (tỷ lệ đạt chỉ tính tháng có chỉ tiêu), API trả pipeline. SỬA LỖI CŨ: dữ liệu mặc định bị tạo lại mỗi lần khởi động → dấu provisioned_at. PipelineTest 12 test.
- [x] 2.6 Tìm kiếm toàn cục (16/10): dịch vụ GlobalSearch cho 11 loại (khách hàng, liên hệ, lead, cơ hội, báo giá, đơn, hóa đơn, sản phẩm, ticket, công việc, nhân viên) — mỗi loại qua quyền xem + visibleTo + phạm vi tổ chức; số điện thoại khớp mọi cách viết (dấu cách/chấm/gạch, +84 hay 0); ký tự đại diện LIKE được thoát. Ô tìm: gợi ý theo nhóm, phím mũi tên, Enter mở trang kết quả, "Xem tất cả", tìm gần đây khi ô trống (xóa được). Trang /search/results: đếm theo loại, lọc loại, phân trang. Điện thoại: nút tìm kiếm trên thanh trên. Bảng search_histories (riêng người dùng + tổ chức, tối đa 10). Phản hồi gợi ý không lặp lại từ khóa. GlobalSearchTest 10 test.
- [x] 2.7 Dashboard điều hành (16/10): khung "Chỉ số điều hành" 20 chỉ số / 4 nhóm (App\Services\ExecutiveMetrics) — mỗi chỉ số có công thức (bấm ⓘ, chạy cả trên điện thoại), so sánh kỳ trước cùng độ dài, nhãn "hiện tại" cho chỉ số không phụ thuộc kỳ, "—" kèm lý do khi chưa có dữ liệu, nhãn DEMO theo nhóm, nhóm ẩn khi không có quyền; bộ lọc thêm Hôm qua & 7 ngày qua; giới hạn ngày tùy chọn. Lead đủ điều kiện tính cả lead chuyển đổi thẳng. Tài liệu docs/DASHBOARD_METRICS.md. ExecutiveMetricsTest 7 test (dữ liệu mẫu cố định). Không đổi CSDL. 260 test.
- [ ] 2.8 Hoàn thiện, v2.0.0
