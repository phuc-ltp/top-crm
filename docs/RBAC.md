# Phân quyền 2 tầng (bước 1.5 — 16/10/2026)

## Tầng nền tảng
- **Quản trị nền tảng** (*Platform Super Administrator*): cờ `users.is_platform_admin`. Có toàn quyền trên **mọi** tổ chức (`Gate::before`). Đây không phải vai trò thông thường nên không thể gán qua giao diện.
- Người khởi tạo hệ thống (trang `/setup` hoặc lệnh `crm:create-admin`) là quản trị nền tảng, đồng thời là chủ sở hữu tổ chức mặc định.

## Tầng tổ chức — 14 vai trò, mỗi tổ chức một bộ riêng
| Vai trò | Tên hiển thị | Số quyền mặc định |
|---|---|---|
| Organization Owner | Chủ sở hữu tổ chức | toàn bộ (khóa, không chỉnh/xóa được) |
| Organization Administrator | Quản trị tổ chức | toàn bộ |
| Sales Manager / Sales Employee | Quản lý / Nhân viên bán hàng | 70 / 30 |
| Marketing Manager / Marketing Employee | Quản lý / Nhân viên marketing | 35 / 26 |
| Customer Support Manager / Support Agent | Quản lý CSKH / Nhân viên hỗ trợ | 33 / 23 |
| Finance Manager / Accountant | Quản lý tài chính / Kế toán | 30 / 21 |
| Inventory Manager | Quản lý kho | 24 |
| Project Manager | Quản lý dự án | 21 |
| Employee | Nhân viên | 10 |
| Read-only Auditor | Kiểm toán (chỉ xem) | 30 (gồm xem nhật ký) |

*Số quyền tính theo `PermissionCatalog` sau bước 4.2 (103 quyền). Quyền nhân viên & nhóm (4.2): mọi vai trò có `employees.view` (danh bạ, thông tin công việc) và `teams.view`; chỉ chủ sở hữu / quản trị có `employees.view_sensitive` (thông tin cá nhân, mã hóa), `employees.update`, `employees.export`, `teams.manage` (nhóm, chi nhánh). Người có quyền sửa nhưng không có quyền xem nhạy cảm thì không thấy và không ghi đè được trường nhạy cảm. Quyền dự án `projects.*`: chủ sở hữu, quản trị và quản lý dự án có toàn bộ; các quản lý bán hàng / marketing / CSKH được xem, tạo, sửa; nhân viên chỉ xem dự án mình là thành viên; kiểm toán xem tất cả.*

- Dùng chế độ **teams** của spatie/laravel-permission, với team = `organization_id`. Bộ xác định `App\Support\Tenancy\OrganizationTeamResolver` lấy tổ chức từ `CurrentOrganization`, nên quyền luôn khớp với tổ chức đang làm việc.
- Mỗi tổ chức có bản vai trò riêng (`App\Support\Tenancy\RoleProvisioner`). Chỉnh quyền ở tổ chức này không ảnh hưởng tổ chức khác. Cùng một người có thể giữ vai trò khác nhau ở các tổ chức khác nhau.
- Tên vai trò tự đặt chỉ cần duy nhất trong tổ chức. Trang Vai trò chỉ hiện và chỉ sửa được vai trò của tổ chức hiện tại; vai trò của tổ chức khác trả về 404.
- Bảo vệ tài khoản: quản trị tổ chức không gán được vai trò chủ sở hữu và không sửa/xóa được chủ sở hữu hay quản trị nền tảng. Chủ sở hữu không tự bỏ được vai trò của mình.

## Ánh xạ vai trò cũ (đổi tên, giữ nguyên quyền và người được gán)
| Cũ | Mới |
|---|---|
| Super Admin | Organization Owner + cờ quản trị nền tảng |
| Administrator | Organization Administrator |
| Sales Manager | Sales Manager |
| Sales Staff | Sales Employee |
| Marketing Staff | Marketing Employee |
| Customer Support | Support Agent |
| Viewer | Read-only Auditor |

## Kiểm chứng
- Migration `2026_10_16_100000_rbac_teams_and_platform_admin` đã thử trên bản sao CSDL thật: 3 vòng migrate → rollback, cấu trúc và tên vai trò sau rollback giống hệt bản gốc. Migration cũng xử lý được cài đặt mới, nơi spatie đã tạo sẵn cột team.
- `tests/Feature/RbacTest.php` (12 test): 14 vai trò cho mỗi tổ chức, tạo lại không ghi đè tùy chỉnh, cô lập quyền giữa tổ chức, một người nhiều vai trò theo tổ chức, quyền nền tảng, bảo vệ chủ sở hữu.
- Sau khi chạy lệnh tạo vai trò: `php artisan db:seed --class=RolePermissionSeeder` (an toàn khi chạy lại).
