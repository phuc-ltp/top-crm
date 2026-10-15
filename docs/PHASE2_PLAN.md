# Phase 2 — Lõi CRM: kế hoạch & tiêu chí nghiệm thu

*Căn cứ: Master Prompt TOP CRM (14/10/2026) — Module 01–04, mục VI (multi-tenant), IX (tìm kiếm toàn cục), XV (nhật ký), XVII (dữ liệu demo). Bắt đầu 17/10/2026.*

Nguyên tắc chung (giữ như Phase 1): làm từng bước trong worktree, migration chỉ thêm và đảo ngược được, thử trên bản sao database thật, sao lưu trước khi chạy database thật, đủ test (quyền + cách ly tổ chức) trước khi gộp, song ngữ Việt/Anh, không có nút không hoạt động, không số liệu giả.

## 2.1 Nền tổ chức: cài đặt & phòng ban theo tổ chức
- Bảng `organization_settings` (khóa–giá trị theo tổ chức). Theo tổ chức: tên/email/điện thoại/địa chỉ/logo công ty, màu thương hiệu, múi giờ, tiền tệ, định dạng ngày, thuế mặc định, bật/tắt thông báo. Giữ chung toàn hệ thống: trang đăng nhập & thương hiệu nền tảng, Google/Facebook, trang pháp lý, cờ khởi tạo, bắt buộc 2FA.
- Phòng ban theo tư cách thành viên: `organization_user.department_id` (chuyển dữ liệu cũ sang).
- Tổ chức mới nhận giá trị mặc định; PDF, email, giao diện dùng cài đặt của tổ chức đang làm việc.
- **Nghiệm thu:** đổi tên công ty ở tổ chức A không đổi ở tổ chức B; một người thuộc 2 tổ chức có phòng ban khác nhau ở mỗi nơi; dữ liệu cũ giữ nguyên giá trị.

## 2.2 Hoạt động & dòng thời gian
- Nâng `interactions` thành hoạt động đầy đủ: loại (ghi chú, gọi, email, gặp, việc theo dõi), thời điểm hẹn, người phụ trách, trạng thái xong/chưa, kết quả; nhắc khi đến hạn.
- Thành phần **dòng thời gian** dùng chung cho khách hàng, lead, cơ hội: hoạt động + thay đổi quan trọng + chứng từ + ticket, sắp theo thời gian.
- **Nghiệm thu:** tạo/sửa/hoàn thành hoạt động theo quyền; nhắc đúng hạn theo tổ chức; dòng thời gian không lộ bản ghi người dùng không được xem.

## 2.3 Khách hàng
- Trường mới: quốc gia, phân khúc, giai đoạn vòng đời (theo dõi lịch sử), kênh liên lạc ưa thích, đồng ý nhận marketing (thời điểm + nguồn), **trường tùy biến** do tổ chức định nghĩa.
- **Gộp khách hàng** (chuyển toàn bộ liên hệ, cơ hội, chứng từ, ticket, hoạt động, thẻ sang bản giữ lại; ghi nhật ký; không xóa cứng).
- Tệp đính kèm, nhập **Excel (.xlsx)** ngoài CSV, bộ lọc nâng cao (phân khúc, vòng đời, quốc gia, thẻ, khoảng ngày), sắp xếp theo cột.
- **Nghiệm thu:** gộp 2 khách hàng không mất bản ghi liên quan nào, có nhật ký; trường tùy biến của tổ chức A không xuất hiện ở B; nhập/xuất giữ đủ trường mới.

## 2.4 Leads
- **Giai đoạn lead tự cấu hình theo tổ chức** (mặc định: Mới → Đã liên hệ → Đủ điều kiện → Đàm phán → Đã chuyển đổi / Mất), lý do mất cấu hình được, đánh giá đủ điều kiện.
- Nhắc theo dõi (ngày theo dõi tiếp theo + thông báo), phát hiện trùng khi tạo, phân tích lead (theo nguồn, giai đoạn, tỷ lệ chuyển đổi).
- **Form thu lead từ website**: đường dẫn công khai riêng mỗi tổ chức (mã bí mật, bật/tắt, chống spam bằng honeypot + giới hạn tần suất).
- **Nghiệm thu:** đổi giai đoạn ở tổ chức A không ảnh hưởng B; lead cũ chuyển sang giai đoạn tương ứng; form website tạo lead đúng tổ chức, chặn spam.

## 2.5 Pipeline & cơ hội (deals)
- **Nhiều pipeline** mỗi tổ chức, giai đoạn thuộc pipeline (pipeline hiện tại thành "Mặc định").
- Sản phẩm trong cơ hội (giá trị tính từ dòng sản phẩm hoặc nhập tay), lý do thắng/thua, lịch theo dõi, tệp đính kèm.
- Dự báo doanh thu (giá trị × xác suất theo tháng dự kiến chốt), so với chỉ tiêu.
- **Nghiệm thu:** kéo thả lưu đúng và kiểm tra quyền; không kéo cơ hội sang giai đoạn của pipeline khác; số dự báo khớp công thức.

## 2.6 Tìm kiếm toàn cục
- Thêm sản phẩm, đơn hàng, báo giá, công việc, nhân viên; nhóm theo loại, gợi ý khi gõ, tìm gần đây (theo người dùng), trang kết quả đầy đủ có lọc theo loại.
- **Nghiệm thu:** mọi kết quả qua kiểm tra quyền và tổ chức (có test cho từng loại).

## 2.7 Dashboard điều hành
- Đủ 20 chỉ số (khách hàng mới, lead đủ điều kiện, cơ hội đang mở, thắng/thua, doanh thu & tăng trưởng, tỷ lệ chuyển đổi, giá trị deal trung bình, giá trị pipeline, việc quá hạn, hóa đơn chờ, công nợ, ticket, hiệu suất nhân viên, chỉ tiêu tháng, đạt KPI, giữ chân khách hàng, dự báo).
- Bộ lọc: hôm nay, hôm qua, 7 ngày, 30 ngày, tháng này, quý này, năm nay, tùy chọn.
- Mỗi chỉ số có **công thức hiển thị** (biểu tượng ⓘ) và trạng thái rỗng có ý nghĩa; dữ liệu demo luôn được gắn nhãn.
- **Nghiệm thu:** mỗi chỉ số có test tính đúng trên dữ liệu mẫu cố định; nhân viên chỉ thấy số liệu trong phạm vi quyền.

## 2.8 Hoàn thiện Phase 2
- Rà từng module theo checklist Master Prompt (danh sách, tạo, xem, sửa, lưu trữ, tìm, lọc, sắp xếp, phân trang, nhập/xuất, quyền, nhật ký, liên kết, thông báo, lỗi, test).
- Cập nhật dữ liệu demo, tài liệu, file SQL; chạy toàn bộ test; đánh dấu **v2.0.0**.

## Quyết định đã chọn (có thể đổi nếu bạn muốn)
- Trường tùy biến lưu dạng JSON trên bản ghi + bảng định nghĩa trường theo tổ chức (đơn giản, đủ cho lọc cơ bản).
- Form website hoạt động ngay trên máy; chỉ dùng được từ Internet khi đến Phase 7.
- Tiền tệ: một đơn vị tiền tệ mỗi tổ chức (VND mặc định), làm tròn 2 chữ số thập phân khi lưu, hiển thị theo tiền tệ.
