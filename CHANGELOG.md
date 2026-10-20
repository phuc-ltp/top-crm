# Nhật ký thay đổi — TOP CRM

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/). Ngày theo giờ Việt Nam.

## [Chưa phát hành] — Phase 4 Vận hành đội nhóm

### Thêm mới
- **Thông báo:** 7 nhóm, trang thông báo mới, chuông tự cập nhật, tùy chọn nhận theo từng người (ứng dụng / email), thông báo có thanh toán và hóa đơn đã thu đủ, tự dọn thông báo cũ.
- **4.1 Dự án & công việc nâng cao:** dự án (thành viên, tiến độ, khách hàng, ngân sách), việc con một cấp, phụ thuộc giữa công việc (chặn vòng lặp, chặn hoàn thành khi việc trước chưa xong), bảng Kanban kéo thả, trang khối lượng công việc, lọc công việc theo dự án, xuất Excel dự án. Quyền mới `projects.*`.

### Sửa lỗi
- Sửa công việc mà biểu mẫu không gửi trường dự án / việc cha thì giữ nguyên liên kết cũ (trước đó có nguy cơ gỡ mất).

## [3.0.0] — Phase 3 Vận hành bán hàng — 19/10/2026

### Thêm mới
- **3.1 Tiền chính xác & danh mục sản phẩm**
  - Mọi phép tính tiền (chứng từ, thanh toán, giá vốn) dùng số thập phân chính xác (bcmath). Đã tính lại các chứng từ cũ, không lệch.
  - Sản phẩm có thêm loại *sản phẩm / dịch vụ*, giá vốn (chỉ người sửa sản phẩm thấy) và lợi nhuận gộp.
  - **Nhà cung cấp**: danh sách, tìm, lọc, xuất, thùng rác, tìm kiếm toàn cục.
  - **Biến thể sản phẩm** (sản phẩm con một cấp, có SKU và giá riêng).
  - **Bảng giá theo nhóm khách hàng**: giá được điền sẵn khi lập chứng từ.
  - Lịch sử thay đổi sản phẩm.
- **3.2 Kho hàng**
  - Nhiều kho; mỗi tổ chức có sẵn "Kho chính".
  - Phiếu nhập, xuất, kiểm kê, chuyển kho; ghi sổ ngay và không sửa / xóa được.
  - Sổ kho bất biến.
  - Giá vốn bình quân gia quyền.
  - Chặn tồn âm (cài đặt cho phép bật).
  - Cảnh báo tồn thấp.
  - Quyền `inventory.*`; giá vốn và giá trị tồn chỉ người quản lý kho thấy.
  - Đã thử thật 3 tiến trình xuất cùng lúc: đúng 1 thành công, tồn khớp sổ kho.
- **3.3 Đơn hàng ↔ kho** (bật trong *Cài đặt › Kho hàng*, mặc định tắt)
  - Duyệt đơn: giữ hàng.
  - Hoàn thành: tự lập phiếu xuất kho.
  - Hủy: trả phần đang giữ.
  - Trả hàng sau khi giao: phiếu nhập hàng trả lại.
  - Lịch sử sửa dòng chứng từ.
- **3.4 Báo giá**
  - Duyệt chiết khấu vượt ngưỡng của tổ chức: chờ duyệt thì giữ ở Nháp và chưa tạo đơn được.
  - Mẫu báo giá.
  - Báo giá quá hạn hiệu lực tự chuyển "Hết hạn".
- **3.5 Hóa đơn & thanh toán**
  - Phiếu giảm trừ (credit note).
  - Hoàn tiền (khoản thanh toán âm, doanh thu tự trừ).
  - Còn phải thu = tổng − giảm trừ − đã thu.
  - Trang **Đối soát thanh toán**.
  - Nhắc hóa đơn sắp đến hạn / quá hạn.
  - Danh sách thuế suất VAT theo tổ chức.
  - Nhật ký tài chính trên hóa đơn.
- **3.6 Báo cáo & hoàn thiện**
  - 3 báo cáo mới: *Bán hàng theo sản phẩm* (lãi gộp chỉ cho người xem giá vốn), *Nhập – xuất – tồn kho*, *Công nợ phải thu theo tuổi nợ*.
  - Dữ liệu demo cho kho và nhà cung cấp.
  - Lệnh xóa demo dọn cả dữ liệu kho.

### Thay đổi
- Ô thuế VAT trên chứng từ thành danh sách chọn khi tổ chức cấu hình thuế suất.
- Chỉ số "Doanh thu" trên Tổng quan trừ các khoản hoàn tiền; "Công nợ" trừ giảm trừ.
- `START_CRM.bat` luôn bật bộ chạy tác vụ định kỳ (nhắc việc, báo giá hết hạn, nhắc thanh toán, cảnh báo tồn thấp), kể cả khi dùng qua Apache.

### Sửa lỗi / bảo mật
- **Lỗ hổng cũ:** nhân viên không có quyền duyệt từng đặt được đơn chưa duyệt sang "Đang xử lý / Hoàn thành". Nay bị chặn.
- **Hóa đơn là chứng từ nội bộ:** trên hóa đơn ghi rõ đây không phải hóa đơn điện tử hợp lệ theo quy định thuế Việt Nam.
- **Sự cố 16/10 (đã khắc phục):** nạp nhầm file SQL mẫu vào database thật. Đã có `scripts/verify-sql.ps1` để không lặp lại.
## [2.0.0] — Phase 2 CRM Core — 16/10/2026

### Thêm mới
- **2.1 Cài đặt & phòng ban theo tổ chức:**
  - Mỗi tổ chức có thông tin công ty, múi giờ, tiền tệ, thuế và thông báo riêng.
  - Phòng ban gắn theo tư cách thành viên, nên một người ở 2 tổ chức có thể thuộc phòng ban khác nhau.
- **2.2 Hoạt động & dòng thời gian:**
  - Ghi nhận hoặc lên lịch cuộc gọi, email, cuộc họp, việc theo dõi; có người phụ trách, kết quả và nhắc trước giờ hẹn.
  - Trang *Hoạt động & lịch hẹn*.
  - Dòng thời gian trên khách hàng, lead và cơ hội.
- **2.3 Khách hàng:**
  - Trường mới: quốc gia, phân khúc, vòng đời, kênh liên lạc, đồng ý marketing (ghi thời điểm và nguồn).
  - Trường tùy biến theo tổ chức.
  - Gộp khách hàng trùng.
  - Nhập Excel (.xlsx), tệp đính kèm, lọc nâng cao.
- **2.4 Leads:**
  - Giai đoạn lead do tổ chức tự cấu hình.
  - Lý do thất bại, hẹn theo dõi, phát hiện trùng.
  - Form thu lead công khai từ website (chống spam).
  - Trang phân tích lead.
- **2.5 Pipeline & cơ hội:**
  - Nhiều pipeline mỗi tổ chức.
  - Sản phẩm trong cơ hội, lý do thất bại bắt buộc.
  - Trang cấu hình pipeline, trang Dự báo so với chỉ tiêu.
- **2.6 Tìm kiếm toàn cục:**
  - 11 loại dữ liệu, gợi ý theo nhóm, phím mũi tên, trang kết quả lọc theo loại.
  - Tìm gần đây (riêng từng người).
  - Số điện thoại viết kiểu nào cũng tìm được.
  - Nút tìm kiếm trên điện thoại.
- **2.7 Dashboard điều hành:**
  - 20 chỉ số chia 4 nhóm, công thức ở biểu tượng ⓘ.
  - So sánh với kỳ trước cùng độ dài.
  - Bộ lọc Hôm qua / 7 ngày qua.
  - Nhãn DEMO; hiện "—" kèm lý do khi chưa có dữ liệu. Danh sách công thức: `docs/DASHBOARD_METRICS.md`.
- **2.8 Hoàn thiện:**
  - **Thùng rác:** xem và khôi phục bản ghi đã xóa của 11 module, theo quyền xóa và phạm vi xem. Nhật ký ghi "Khôi phục".
  - **Nhập CSV / Excel** cho lead, người liên hệ, sản phẩm (kiểm tra từng dòng như nhập tay, bỏ qua dòng trùng, báo lỗi theo số dòng, cả tệp lưu trong một lần).
  - **Xuất Excel** danh sách công việc.
  - Dữ liệu demo bổ sung cho tính năng Phase 2.

### Thay đổi
- Thông báo khi xóa bản ghi nhắc rằng có thể khôi phục trong Thùng rác.
- Phản hồi gợi ý tìm kiếm không lặp lại từ khóa đã gõ.
- Chỉ số "Lead đủ điều kiện" tính cả lead được chuyển đổi thẳng, theo ngày chuyển đổi.

### Sửa lỗi
- **Lỗ hổng cũ:** trang chi tiết khách hàng, cơ hội và lead từng hiện bản ghi liên quan mà người xem không có quyền xem (sửa ở 2.2).
- Dữ liệu cấu hình mặc định bị tạo lại mỗi lần khởi động, kể cả khi người dùng đã xóa (sửa ở 2.5).
- Lệnh `crm:purge-demo`:
  - không dọn hoạt động gắn với lead, cơ hội và người liên hệ demo;
  - cờ "có dữ liệu demo" vẫn còn trong bộ nhớ đệm sau khi xóa.

### Bảo mật
- Mọi tính năng mới đều có kiểm thử về quyền, phạm vi "chỉ của mình" và cách ly giữa các tổ chức.
- Lịch sử tìm kiếm là riêng tư theo người dùng và tổ chức.
- Không có chức năng xóa vĩnh viễn trong Thùng rác.
- `composer audit` và `npm audit`: 0 lỗ hổng (16/10/2026).
## [1.0.0] — Phase 1 Foundation — 17/10/2026

### Thêm mới
- **1.0 MariaDB 10.11 LTS** (từ 10.4.32): dữ liệu khớp 100%, chỉ lắng nghe `127.0.0.1`. Hướng dẫn hoàn tác: `docs/MARIADB_UPGRADE.md`.
- **1.1 Khóa trang /setup vĩnh viễn** sau khi có quản trị đầu tiên (cờ trong DB + khóa đồng thời; đã thử 12 yêu cầu song song → chỉ 1 tài khoản). Phiên đăng nhập được mã hóa.
- **1.2 Song ngữ Việt / Anh** cho toàn bộ giao diện (~1.300 câu), bộ chọn ngôn ngữ, lưu theo tài khoản, email theo ngôn ngữ người nhận, trang pháp lý tiếng Anh.
- **1.3 Tổ chức (multi-tenancy):** bảng `organizations`, `organization_user`, cột `organization_id` trên 21 bảng nghiệp vụ, mã/unique theo tổ chức.
- **1.4 Cách ly dữ liệu theo tổ chức:** global scope fail-closed, chặn đổi tổ chức của bản ghi, `TenantRule` cho validation, nhắc việc theo tổ chức.
- **1.5 Phân quyền 2 tầng:** quản trị nền tảng + 14 vai trò riêng mỗi tổ chức (spatie teams).
- **1.6 Không gian làm việc:** chuyển tổ chức, quản lý tổ chức (nền tảng), thêm tài khoản có sẵn bằng email, tạm ngưng / gỡ thành viên.
- **1.7 Bố cục:** ô tìm chức năng trong menu, giao diện sáng/tối lưu theo tài khoản, sửa tràn ngang trên điện thoại.
- **1.8 Bảo mật tài khoản:** xác thực 2 lớp (TOTP + mã khôi phục), lịch sử đăng nhập, cảnh báo thiết bị mới, xác minh email, quy định mật khẩu chung, tùy chọn bắt buộc 2FA cho quản trị.
- **1.9 API bằng khóa (Sanctum):** khóa gắn tổ chức, 6 phạm vi, có thời hạn, quyền không vượt người tạo, trang quản lý/thu hồi khóa.
- **1.10 Tài liệu:** `ARCHITECTURE.md` (cập nhật), `DATABASE.md` (ERD), `SECURITY.md`, `TESTING.md`, `CHANGELOG.md`; script `scripts/generate-sql.ps1` và file SQL mới.
- Sao lưu một chạm `SAO_LUU.bat` (database, tệp tải lên, `.env`, Git) + kho GitHub riêng tư.

### Thay đổi
- Đổi tên vai trò cũ (giữ nguyên quyền và người dùng): Super Admin → Organization Owner + cờ quản trị nền tảng; Administrator → Organization Administrator; Sales Staff → Sales Employee; Marketing Staff → Marketing Employee; Customer Support → Support Agent; Viewer → Read-only Auditor.
- Mật khẩu mới tối thiểu 10 ký tự (trước đây 8 ở một số nơi); mật khẩu đang dùng vẫn hợp lệ.
- `/api/v1` chuyển sang `auth:sanctum` (vẫn hỗ trợ phiên web cùng tên miền); mỗi route yêu cầu phạm vi tương ứng.
- Middleware xác định tổ chức chạy trước khi tìm bản ghi trên route (web và API).

### Bảo mật
- Khóa bí mật 2FA và secret OAuth mã hóa bằng `APP_KEY`; mã khôi phục, khóa API, cookie thiết bị chỉ lưu băm.
- Nhật ký hoạt động không ghi mật khẩu, mã 2FA, khóa API.
- `composer audit` và `npm audit`: 0 lỗ hổng (17/10/2026).

### Hạn chế đã biết (chuyển sang Phase 2)
- Cài đặt hệ thống và phòng ban của người dùng còn dùng chung giữa các tổ chức.
- Chưa cấu hình gửi email thật (thư được ghi vào `storage/logs/laravel.log`).
- Mã nghiệp vụ dựa trên id toàn hệ thống (không liên tục trong từng tổ chức).
- Nội dung nhật ký/thông báo đã lưu bằng tiếng Việt.

## [0.9.0] — Bản CRM đầy đủ module — 13–15/10/2026
- Khách hàng, liên hệ, leads (chấm điểm, chuyển đổi), pipeline Kanban, công việc, báo giá → đơn hàng → hóa đơn → thanh toán (PDF), sản phẩm, marketing, hỗ trợ khách hàng, 8 báo cáo, thông báo, nhật ký, cài đặt.
- Trang đăng nhập thương hiệu TOP (máy tính + điện thoại), đăng nhập Google/Facebook cho tài khoản đã cấp.
- Giao diện kính kiểu macOS, sáng/tối, dashboard dữ liệu thật.
