# Chỉ số điều hành trên trang Tổng quan (bước 2.7)

Trang **Tổng quan** có khung "Chỉ số điều hành" gồm 20 chỉ số chia 4 nhóm. Bấm biểu tượng ⓘ cạnh mỗi chỉ số để xem công thức ngay trên màn hình.

## Nguyên tắc chung

- **Nguồn số liệu:** mọi số tính trực tiếp từ CSDL, không có số nhập tay hay số giả.
- **Phạm vi quyền:** mỗi người chỉ thấy số liệu trong phạm vi mình được xem. Nhân viên thấy số của chính mình, quản lý thấy cả đội. Số liệu của tổ chức khác không bao giờ được tính.
- **Nhóm theo quyền:** nhóm chỉ số chỉ hiện khi người xem có quyền xem module đó. Ví dụ: không có quyền xem hóa đơn thì không thấy nhóm Tài chính.
- **Khi chưa có dữ liệu:** chỉ số hiện "—" kèm lý do (ví dụ "Chưa đặt chỉ tiêu cho tháng này"). Hệ thống không bao giờ chia cho 0.
- **Nhãn DEMO:** nhóm nào có tính cả dữ liệu demo thì hiện nhãn **DEMO**.
- **Kỳ lọc:** hôm nay, hôm qua, 7 ngày qua, 30 ngày qua, tháng này, tháng trước, quý này, năm nay, hoặc tùy chọn ngày.
- **So sánh:** "kỳ trước" là khoảng liền trước có cùng số ngày. Ví dụ: kỳ 01/06–30/06 so với 02/05–31/05.
- **Chỉ số "hiện tại":** các chỉ số gắn nhãn "hiện tại" là trạng thái ngay lúc xem, không phụ thuộc kỳ lọc.

## Danh sách chỉ số

| Nhóm | Chỉ số | Công thức |
|---|---|---|
| Khách hàng & Lead | Khách hàng mới | Số khách hàng được tạo trong kỳ |
| | Lead mới | Số lead được tạo trong kỳ |
| | Lead đủ điều kiện | Lead đạt giai đoạn "đủ điều kiện" trong kỳ, tính theo ngày đạt. Lead chuyển đổi thẳng thì tính theo ngày chuyển đổi |
| | Tỷ lệ chuyển đổi lead | Lead tạo trong kỳ đã chuyển thành khách hàng ÷ lead tạo trong kỳ |
| | Giữ chân khách hàng | Khách có đơn (trừ đơn hủy) ở kỳ trước và mua lại kỳ này ÷ khách có đơn ở kỳ trước |
| Bán hàng | Cơ hội đang mở *(hiện tại)* | Cơ hội không ở giai đoạn Thắng hoặc Thất bại |
| | Giá trị pipeline *(hiện tại)* | Tổng giá trị cơ hội đang mở |
| | Dự báo doanh số kỳ | Giá trị đã thắng trong kỳ + Σ(giá trị × xác suất) của cơ hội mở dự kiến chốt trong kỳ. Cùng công thức với trang Dự báo |
| | Thắng / thua trong kỳ | Số cơ hội đóng Thắng / Thất bại trong kỳ, theo ngày đóng |
| | Tỷ lệ thắng | Thắng ÷ (thắng + thất bại) trong kỳ |
| | Giá trị deal trung bình | Tổng giá trị thắng ÷ số cơ hội thắng trong kỳ |
| Tài chính | Doanh thu (đã thu) | Tiền khách đã thanh toán trong kỳ cho hóa đơn đã phát hành, trừ các khoản hoàn tiền |
| | Tăng trưởng doanh thu | (Kỳ này − kỳ trước) ÷ kỳ trước |
| | Hóa đơn chờ thanh toán *(hiện tại)* | Hóa đơn đã phát hành chưa thanh toán đủ, kèm số hóa đơn quá hạn |
| | Công nợ phải thu *(hiện tại)* | Σ(tổng tiền − giảm trừ − đã thanh toán) của các hóa đơn đó, kèm phần quá hạn |
| Đội ngũ & vận hành | Công việc quá hạn *(hiện tại)* | Việc chưa xong đã qua hạn |
| | Hoàn thành đúng hạn | Việc có hạn trong kỳ hoàn thành đúng hoặc trước hạn ÷ việc có hạn trong kỳ (trừ việc hủy) |
| | Ticket đang mở *(hiện tại)* | Ticket Mới mở, Đang xử lý hoặc Chờ phản hồi, kèm số ticket mới trong kỳ |
| | Hoàn thành chỉ tiêu | Doanh thu đã thu trong tháng ÷ chỉ tiêu tháng. Tháng là tháng chứa ngày cuối kỳ |
| | Nhân viên đạt chỉ tiêu | Số nhân viên có doanh thu (trên hóa đơn mình phụ trách) ≥ chỉ tiêu của mình ÷ số nhân viên có chỉ tiêu |

## Kiểm thử

`tests/Feature/ExecutiveMetricsTest.php` dựng một bộ dữ liệu mẫu cố định, coi "hôm nay" là 15/06/2026, rồi so từng chỉ số với con số đã tính tay. Bộ kiểm thử cũng kiểm tra:

- nhân viên chỉ thấy số của mình;
- nhóm không có quyền bị ẩn;
- tổ chức trống hiện "—";
- nhãn DEMO;
- bộ lọc kỳ;
- cách ly giữa các tổ chức.

Mã nguồn tính toán nằm ở `app/Services/ExecutiveMetrics.php`.
