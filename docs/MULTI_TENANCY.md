# Đa tổ chức (multi-tenant)

TOP CRM dùng mô hình **một CSDL dùng chung + cột `organization_id`**: mỗi doanh nghiệp là một *tổ chức*, mọi dữ liệu nghiệp vụ thuộc về đúng một tổ chức.

## Cấu trúc (bước 1.3 — 15/10/2026)
| Bảng / cột | Ý nghĩa |
|---|---|
| `organizations` | Tổ chức: tên, slug, trạng thái (active/suspended), chủ sở hữu (`owner_id`) |
| `organization_user` | Thành viên: tổ chức ↔ người dùng, trạng thái (active/invited/suspended), ngày tham gia |
| `users.current_organization_id` | Tổ chức người dùng đang làm việc |
| `organization_id` trên 21 bảng | departments, lead_sources, tags, pipeline_stages, product_categories, products, customers, contacts, leads, campaigns, opportunities, quotations, sales_orders, invoices, payments, tasks, support_tickets, interactions, attachments, sales_targets, activity_logs (cho phép trống) |

- Bảng con (dòng chứng từ, lịch sử giai đoạn, checklist, bình luận, phản hồi ticket, lead của chiến dịch, thẻ khách hàng) thuộc tổ chức **thông qua bảng cha**.
- Khóa duy nhất chuyển thành **duy nhất trong từng tổ chức**: mã khách hàng/lead/cơ hội/ticket, số báo giá/đơn/hóa đơn, SKU, tên nguồn/thẻ/danh mục, mã phòng ban, slug giai đoạn, chỉ tiêu (người dùng + tháng).
- Khóa ngoại `organization_id` dùng `RESTRICT`: không xóa được tổ chức còn dữ liệu.
- Dữ liệu có sẵn được gán vào **tổ chức mặc định (id = 1)**. Người khởi tạo hệ thống là chủ sở hữu.

## Trong mã nguồn
- `App\Models\Organization`, trait `App\Models\Concerns\BelongsToOrganization` (tự gán `organization_id` khi tạo bản ghi).
- `App\Support\Tenancy\CurrentOrganization`: tổ chức đang làm việc, theo thứ tự đặt rõ ràng → tổ chức hiện tại của người dùng → tổ chức mặc định. `run($orgId, fn () => ...)` chạy mã trong ngữ cảnh một tổ chức (job, seeder, test).
- `organization_id` **không** nằm trong `$fillable`. Hệ thống không bao giờ nhận giá trị này từ dữ liệu người dùng gửi lên.
- Tài khoản mới tự trở thành thành viên của tổ chức đang làm việc.

## Kiểm chứng
- Migration đã thử trên bản sao CSDL thật: 3 vòng migrate → rollback liên tiếp; cấu trúc sau rollback giống hệt bản gốc.
- `tests/Feature/OrganizationTest.php`: tự gán tổ chức, chặn gán hàng loạt, khóa duy nhất theo tổ chức, thành viên, chặn xóa tổ chức còn dữ liệu.

## Phạm vi dữ liệu (bước 1.4)
- `OrganizationScope` (global scope) trên 21 model: mọi truy vấn Eloquent, kể cả route model binding, tìm kiếm, xuất file, báo cáo, dashboard và API, tự giới hạn trong tổ chức đang làm việc. ID của tổ chức khác trả về **404**. Không xác định được tổ chức thì **không trả về gì**.
- Không thể chuyển bản ghi sang tổ chức khác (ném `LogicException`). Truy vấn xuyên tổ chức phải gọi rõ `Model::withoutOrganizationScope()` và chỉ dành cho tác vụ cấp nền tảng.
- Middleware `SetCurrentOrganization` kiểm tra người dùng là thành viên đang hoạt động của một tổ chức đang hoạt động. Nếu không còn tổ chức hợp lệ nào thì đăng xuất.
- `TenantRule::exists()/unique()/member()` thay các luật `exists`/`unique`: không thể tham chiếu bản ghi hay giao việc cho thành viên của tổ chức khác.
- Người dùng: `User::inOrganization()` cho danh sách và ô chọn; `{user}` trên route chỉ tìm trong thành viên của tổ chức.
- Bảng con được truy cập qua bản ghi cha (đã lọc) và controller có kiểm tra con thuộc đúng cha.
- Tác vụ nền `crm:task-reminders` chạy lần lượt trong từng tổ chức đang hoạt động.
- Kiểm thử: `tests/Feature/TenantIsolationTest.php` (15 test) mô phỏng tổ chức A cố truy cập dữ liệu tổ chức B qua danh sách, trang chi tiết, sửa/xóa, kéo thả Kanban, tìm kiếm, 7 loại xuất file, báo cáo, dashboard, API, form tham chiếu, giao việc, checklist/ghi chú, đổi tổ chức. Chạy trên mã cũ (chưa có bộ lọc) thì 13 test thất bại, chứng tỏ bộ test phát hiện được rò rỉ.

## Chuyển tổ chức & quản lý thành viên (bước 1.6)
- **Chuyển tổ chức:** ô "Không gian làm việc" ở thanh bên liệt kê các tổ chức mà người dùng là thành viên đang hoạt động (`POST /workspace/switch`). Quản trị nền tảng vào được mọi tổ chức đang hoạt động để hỗ trợ.
- **Quản lý tổ chức** (`/platform/organizations`, chỉ quản trị nền tảng): danh sách, tạo tổ chức mới kèm chủ sở hữu (tài khoản có sẵn theo email, hoặc tạo mới), sửa tên/mã, tạm ngưng/kích hoạt. Không tạm ngưng được tổ chức mặc định.
- **Khởi tạo tổ chức** (`OrganizationProvisioner`): 14 vai trò, 5 phòng ban, 9 nguồn khách hàng, 6 giai đoạn bán hàng, danh mục sản phẩm, thẻ. An toàn khi chạy lại và không ghi đè thứ tổ chức đã tự chỉnh.
- **Thành viên dùng chung nhiều tổ chức:**
  - Thêm bằng email đã có tài khoản thì chỉ thêm tư cách thành viên và vai trò, không đổi mật khẩu hay thông tin của họ.
  - "Khóa" = tạm ngưng thành viên trong tổ chức hiện tại (tài khoản vẫn dùng được ở tổ chức khác).
  - "Xóa" người còn thuộc tổ chức khác = gỡ khỏi tổ chức (giữ tài khoản). Chỉ xóa mềm tài khoản khi họ chỉ thuộc một tổ chức.
  - **Chống chiếm tài khoản xuyên tổ chức:** quản trị tổ chức không đổi được email/tên/mật khẩu của tài khoản thuộc nhiều tổ chức; chỉ chủ tài khoản hoặc quản trị nền tảng làm được.
- Cờ "có dữ liệu demo" được cache riêng theo tổ chức.
- Kiểm thử: `tests/Feature/WorkspaceTest.php` (17 test).

## Bước 2.1: Cài đặt & phòng ban theo tổ chức
- **Cài đặt riêng** (`organization_settings`, khóa trong `Setting::ORGANIZATION_KEYS`): tên/email/điện thoại/địa chỉ/logo công ty, màu thương hiệu, múi giờ hiển thị, tiền tệ, định dạng ngày, thuế mặc định, bật/tắt từng loại thông báo. `Setting::get()` / `allCached()` tự lấy theo tổ chức đang làm việc; `Setting::put()` tự ghi vào tổ chức đó. Khóa của tổ chức **không** dự phòng sang giá trị chung (tổ chức mới không mượn thông tin của tổ chức khác). Tổ chức mới: tên công ty = tên tổ chức.
- **Cài đặt chung toàn hệ thống** (bảng `settings`): trang đăng nhập & thương hiệu nền tảng, Google/Facebook, trang pháp lý, cờ khởi tạo, bắt buộc 2FA — **chỉ quản trị nền tảng** chỉnh. Trang đăng nhập (chưa có tổ chức) dùng cài đặt của tổ chức mặc định.
- **Múi giờ:** dùng để hiển thị ngày giờ (`vn_date(..., true)`, `crm_timezone()`); dữ liệu vẫn lưu theo giờ máy chủ; ngày thuần không đổi múi giờ.
- **Phòng ban theo tư cách thành viên:** `organization_user.department_id`; `User::departmentIdIn()`, `$user->department` (tổ chức đang làm việc), `assignDepartment()`. Cột `users.department_id` cũ giữ lại (không còn dùng) để hoàn tác.
- Kiểm thử: `tests/Feature/OrganizationSettingsTest.php` (9 test).

## Còn lại (các bước sau)
- Mã nghiệp vụ (KH00001...) hiện sinh theo id toàn hệ thống, vẫn duy nhất. Đánh số riêng cho từng tổ chức có thể làm sau.
