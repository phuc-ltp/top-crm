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

## Bước tiếp theo
- **1.4:** phạm vi truy vấn tự động theo tổ chức (global scope), middleware xác định tổ chức, kiểm tra luật `unique`/`exists` theo tổ chức, tìm kiếm/báo cáo/xuất file/dashboard/job theo tổ chức, cùng bộ test cô lập dữ liệu giữa các tổ chức.
- Mã nghiệp vụ (KH00001...) hiện sinh theo id toàn hệ thống, vẫn duy nhất. Đánh số riêng cho từng tổ chức có thể làm sau.
