# Phase 4 — Vận hành đội nhóm: kế hoạch & tiêu chí nghiệm thu

*Căn cứ: Master Prompt TOP CRM (14/10/2026) — Phase 4 "Team Operations", Module 09 (Công việc & dự án), 10 (Nhân viên & nhóm), 11 (Hỗ trợ khách hàng & ticket), 14 (Lịch & lịch hẹn), 15 (Quản lý tài liệu). Bắt đầu 19/10/2026.*

## Nguyên tắc
Giữ nguyên các nguyên tắc của Phase 1–3:
- Làm từng bước trong worktree.
- Migration chỉ thêm và đảo ngược được, thử trên bản sao database thật.
- Sao lưu trước khi chạy database thật.
- Có test về quyền và cách ly tổ chức.
- Song ngữ Việt / Anh.
- Không có nút không hoạt động, không có số liệu giả.

Riêng Phase 4:
- **Nâng cấp, không làm lại.** Công việc, ticket, phòng ban và tệp đính kèm đã có. Phase 4 mở rộng các bảng này, giữ nguyên dữ liệu và đường dẫn cũ.
- **Hồ sơ nhân sự là dữ liệu nhạy cảm.** Thông tin như ngày sinh hay địa chỉ chỉ hiện cho người có quyền `employees.view_sensitive`. Danh bạ thường chỉ có tên, chức danh, phòng ban, nhóm và liên hệ công việc.
- **Tệp không truy cập trực tiếp.** Tệp lưu ngoài thư mục `public`. Mọi lượt xem hoặc tải đều qua kiểm tra quyền và được ghi nhật ký. Hệ thống kiểm tra loại tệp theo nội dung thật và giới hạn dung lượng.
- **Chỉ số tính từ dữ liệu thật**, có định nghĩa rõ ràng (KPI, SLA, khối lượng công việc).

## Quyết định mặc định (có thể đổi nếu bạn muốn)
- **SLA mặc định theo mức ưu tiên** (giờ phản hồi đầu / giờ xử lý xong): khẩn cấp 1/8, cao 4/24, trung bình 8/48, thấp 24/96. Mỗi tổ chức chỉnh được. Tính theo giờ đồng hồ, chưa trừ giờ ngoài làm việc.
- **Đánh giá hài lòng (CSAT):** nhân viên ghi nhận điểm 1–5 do khách báo lại. Chưa mở trang công khai cho khách, vì hệ thống không đưa lên Internet.
- **Phụ thuộc công việc:** chỉ dạng "A phải xong trước B". Hệ thống chặn vòng lặp và chặn hoàn thành B khi A chưa xong.
- **Quyền xem tài liệu:** riêng tư (người tải lên), theo nhóm, hoặc toàn công ty. Mặc định là toàn công ty.

## 4.1 Dự án & công việc nâng cao
- Bảng **dự án**: mã, tên, khách hàng liên quan, người quản lý, thành viên, ngày bắt đầu và kết thúc, trạng thái, ngân sách tham khảo. Tiến độ tính từ số công việc đã xong.
- Công việc có thêm: dự án, công việc cha (việc con), ngày bắt đầu, ước tính giờ, thứ tự trên bảng Kanban.
- **Phụ thuộc** giữa các công việc, có chặn vòng lặp.
- **Bảng Kanban** kéo thả đổi trạng thái (kiểm tra quyền ở máy chủ), cùng với dạng danh sách và lịch sẵn có.
- **Tổng quan khối lượng công việc:** số việc đang làm, quá hạn và giờ ước tính của từng người.
- Quyền mới `projects.*`. Thành viên dự án xem được công việc của dự án.
- **Nghiệm thu:**
  - công việc cũ không đổi;
  - không tạo được phụ thuộc vòng;
  - người ngoài tổ chức hoặc không có quyền không xem được dự án;
  - kéo thả lưu đúng trạng thái.

## 4.2 Nhân viên & nhóm
- Hồ sơ nhân viên (bảng `employee_profiles`, 1–1 với người dùng trong tổ chức):
  - mã nhân viên, chức danh, quản lý trực tiếp, chi nhánh;
  - ngày vào làm, trạng thái làm việc;
  - thông tin nhạy cảm, có quyền xem riêng.
- Bảng **chi nhánh**, **nhóm** và **thành viên nhóm** (trưởng nhóm).
- **Danh bạ nhân viên** có tìm kiếm và lọc.
- **Sơ đồ báo cáo**, có chặn vòng lặp quản lý.
- **KPI cá nhân và nhóm** từ dữ liệu thật:
  - doanh thu đã thu;
  - cơ hội thắng;
  - công việc hoàn thành đúng hạn;
  - ticket đã giải quyết;
  - so với chỉ tiêu bán hàng sẵn có.
- Quyền mới `employees.*` và `teams.*`.
- **Nghiệm thu:** người không có quyền không thấy thông tin nhạy cảm, kể cả qua xuất file và tìm kiếm; KPI khớp với truy vấn đối chiếu.

## 4.3 Hỗ trợ khách hàng: SLA, phân loại, leo thang
- **Danh mục ticket.**
- **Chính sách SLA** theo mức ưu tiên. Mỗi ticket lưu hạn phản hồi đầu và hạn xử lý, kèm thời điểm phản hồi đầu thực tế.
- **Đồng hồ SLA** trên giao diện. Thời gian ở trạng thái "Chờ khách" không bị tính.
- **Leo thang tự động** (tác vụ định kỳ): sắp vi phạm thì báo người phụ trách; đã vi phạm thì báo trưởng nhóm hoặc quản lý, đánh dấu đã leo thang, không báo lặp.
- Ticket có thêm:
  - tệp đính kèm;
  - lịch sử đổi trạng thái;
  - điểm hài lòng.
- **Phân tích hỗ trợ:** tỉ lệ đạt SLA, thời gian phản hồi và xử lý trung bình, số ticket theo danh mục và nhân viên, điểm hài lòng trung bình.
- **Nghiệm thu:**
  - SLA tính đúng, có test với thời gian giả lập;
  - leo thang chỉ báo một lần;
  - ticket cũ vẫn hiển thị đúng (SLA để trống).

## 4.4 Lịch & lịch hẹn
- Bảng **lịch hẹn / cuộc họp**:
  - tiêu đề, thời gian bắt đầu và kết thúc, địa điểm hoặc link họp;
  - khách hàng, liên hệ, cơ hội liên quan;
  - người tham dự (nội bộ);
  - trạng thái (đã lên lịch / đã diễn ra / hủy / dời);
  - biên bản cuộc họp.
- **Lịch chung:** lịch hẹn và hạn công việc, xem theo tháng, tuần hoặc ngày, lọc theo người.
- **Nhắc lịch** qua thông báo trước giờ hẹn.
- **Cảnh báo trùng lịch** của người tham dự (xem bận / rảnh).
- Chưa tích hợp Google hay Outlook Calendar vì chưa có tài khoản ứng dụng. Có thể xuất tệp .ics.
- **Nghiệm thu:** cảnh báo trùng lịch đúng; nhắc lịch không gửi lặp; chỉ người tham dự hoặc người có quyền mới xem được chi tiết.

## 4.5 Quản lý tài liệu
- **Thư viện tài liệu** có danh mục, người sở hữu, phạm vi xem và liên kết tới khách hàng hoặc cơ hội.
- **Lịch sử phiên bản:** tải bản mới vẫn giữ các bản cũ.
- **Xem trước** ảnh và PDF qua đường dẫn có kiểm tra quyền.
- **Giới hạn loại và dung lượng tệp**, kiểm tra theo nội dung thật.
- **Tải xuống an toàn**, có ghi nhật ký.
- Tệp đính kèm cũ vẫn hoạt động như hiện nay.
- **Nghiệm thu:**
  - không tải được tài liệu riêng tư của người khác (kể cả đoán ID);
  - tệp giả mạo đuôi bị từ chối;
  - không có đường dẫn công khai tới tệp.

## 4.6 Hoàn thiện Phase 4
- Dữ liệu demo cho các phần mới, chỉ trong tổ chức demo và đánh dấu `is_demo`.
- Báo cáo **năng suất công việc** và **báo cáo ticket** trong trang Báo cáo.
- Tìm kiếm toàn cục gồm dự án, tài liệu và lịch hẹn.
- Tài liệu: CHANGELOG 4.0.0, USER_GUIDE, DATABASE, TESTING.
- File SQL cập nhật và kiểm thử bằng `verify-sql.ps1`.
- **Nghiệm thu Phase 4:** toàn bộ test đạt; ảnh chụp desktop và mobile không lỗi; database thật đã migrate, đối chiếu dữ liệu cũ không đổi.
