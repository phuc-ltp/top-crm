# Phase 5 — Nhân sự & tài sản: kế hoạch & tiêu chí nghiệm thu

*Căn cứ: yêu cầu của người dùng ngày 21/10/2026 (tài khoản nhân viên, chấm công GPS + selfie trên điện thoại, tăng ca, bảng lương theo giờ) và các module tham khảo do thầy Giason gửi (quản lý tài sản, tem QR, bản quyền phần mềm, giờ làm thêm, bảng lương).*

## Nguyên tắc
Giữ nguyên các nguyên tắc của Phase 1–4:
- Làm từng bước trong worktree.
- Migration chỉ thêm, đảo ngược được, thử trên bản sao CSDL thật.
- Sao lưu trước khi chạy CSDL thật.
- Có test quyền và test cách ly tổ chức.
- Song ngữ Việt / Anh.
- Không có số liệu giả.

Riêng Phase 5:
- **Lương là dữ liệu nhạy cảm.** Mỗi người chỉ xem phiếu lương của mình. Chỉ người có quyền `payroll.*` xem lương người khác và bảng lương.
- **Ảnh selfie và vị trí chấm công là dữ liệu cá nhân nhạy cảm** (Nghị định 13/2023):
  - nhân viên phải đồng ý trước khi chấm công lần đầu;
  - ảnh lưu ngoài thư mục `public`, chỉ người có quyền mới xem;
  - có thời hạn lưu.
- **Tiền tính bằng số thập phân chính xác** (bcmath), giống chứng từ bán hàng.
- **Bảng lương đã duyệt thì khóa.** Muốn sửa phải mở lại, và mọi thao tác đều ghi nhật ký.

## Quyết định đã chốt với người dùng (21/10/2026)
- **Lương theo giờ, theo bảng bậc lương** từ 35.000 đ/giờ (thấp nhất) đến 200.000 đ/giờ (cao nhất):
  - mỗi vai trò có bậc mặc định;
  - chỉnh riêng được cho từng người.
- **Giờ làm chuẩn:** 7:30–11:30 và 13:30–17:30, tức 8 giờ mỗi ngày.
- **Tăng ca:** chỉ trong khung 18:00–21:00 (tối đa 3 giờ).
  - Đơn giá theo mức độ công việc: cao 100.000, vừa 70.000, thấp 40.000 đ/giờ.
  - **Không thấp hơn 150% lương giờ của người đó** (Điều 98 Bộ luật Lao động 2019).
- **Chấm công trên điện thoại:**
  - chụp selfie, lấy định vị GPS, kiểm tra trong bán kính văn phòng;
  - quản lý xem lại ảnh khi cần;
  - so khớp khuôn mặt tự động làm ở bước sau.
  - Camera và GPS trên điện thoại cần **HTTPS**, nên chỉ dùng thật được sau khi go-live; kiểm thử được ngay trên máy.
- **Thứ tự làm:** nhân sự trước (tài khoản → chấm công → tăng ca → lương), sau đó đến tài sản, bản quyền.

## 5.1 Tài khoản nhân viên nhanh & bậc lương
- **Tạo nhanh tài khoản nhân viên** từ trang Danh bạ: chỉ cần tên, email, số điện thoại, vai trò, phòng ban (tùy chọn).
  - Hệ thống sinh mật khẩu tạm mạnh, **chỉ hiện một lần**.
  - Nhân viên **bắt buộc đổi mật khẩu** ở lần đăng nhập đầu.
- **Bảng bậc lương** (tên bậc, đơn giá / giờ trong khoảng 35.000–200.000, các vai trò mặc định), có sẵn 7 bậc.
- Hồ sơ nhân viên có bậc lương và đơn giá riêng (nếu có).
- Quyền mới `payroll.view_all`, `payroll.manage`.
- **Nghiệm thu:**
  - mật khẩu tạm không lưu dạng đọc được và không hiện lại;
  - chưa đổi mật khẩu thì không vào được các trang khác;
  - đơn giá giờ của mỗi người tính đúng thứ tự ưu tiên: riêng → bậc trong hồ sơ → bậc theo vai trò → bậc thấp nhất;
  - người không có quyền không xem được đơn giá của người khác.

## 5.2 Chấm công (GPS + selfie)
- **Cài đặt chấm công:**
  - ca làm và khung tăng ca;
  - ngày làm việc trong tuần;
  - số phút cho phép đi trễ;
  - các văn phòng (tọa độ, bán kính).
- **Trang chấm công trên điện thoại:** vào ca / tan ca, chụp selfie, lấy GPS.
  - Hệ thống kiểm tra khoảng cách tới văn phòng ở máy chủ.
  - Quá bán kính thì vẫn ghi nhận nhưng gắn cờ "ngoài vùng" để quản lý duyệt.
- **Bảng công tháng** theo người và theo ngày:
  - số giờ làm thực tế (chỉ tính trong giờ chuẩn), phút đi trễ / về sớm;
  - ngày thiếu công;
  - quản lý điều chỉnh có ghi lý do và nhật ký.
- **Nghiệm thu:**
  - tính giờ đúng với ca chia sáng / chiều;
  - không chấm công thay người khác;
  - ảnh chỉ người có quyền xem;
  - cách ly tổ chức.

## 5.3 Tăng ca (OT)
- **Đơn đề nghị tăng ca:** ngày, giờ (trong khung 18:00–21:00), mức độ công việc, lý do.
  - Quản lý duyệt hoặc từ chối.
  - Có lịch tăng ca.
- Giờ được trả lương = giờ đã duyệt (đối chiếu giờ tan ca thực tế nếu có).
- **Nghiệm thu:** không vượt khung giờ; đơn giá đúng quy tắc 150%; nhân viên không tự duyệt đơn của mình.

## 5.4 Bảng lương
- **Kỳ lương theo tháng:** nháp → chờ duyệt → đã duyệt → đã trả.
  - Tạo từ bảng công và đơn tăng ca đã duyệt.
- **Phiếu lương từng người:**
  - giờ làm × đơn giá, giờ tăng ca × đơn giá tăng ca, phụ cấp / khấu trừ thủ công;
  - BHXH / BHYT / BHTN (8% / 1,5% / 1%), thuế TNCN biểu lũy tiến (giảm trừ bản thân và người phụ thuộc). Phần bảo hiểm và thuế bật / tắt được trong cài đặt;
  - thực nhận.
- Xuất PDF phiếu lương, xuất Excel bảng lương.
- Nhân viên xem phiếu lương của chính mình.
- **Nghiệm thu:**
  - số liệu khớp phép tính kiểm tra bằng tay;
  - kỳ đã duyệt không sửa được;
  - không ai xem được phiếu lương người khác nếu thiếu quyền.

## 5.5 Quản lý tài sản
- **Tổng quan tài sản:** số lượng, nguyên giá, giá trị còn lại, theo trạng thái / nhóm / bộ phận.
- **Danh mục tài sản:** nhóm, vị trí, nhà cung cấp, ngày mua, nguyên giá, khấu hao đường thẳng, trạng thái.
- **Luân chuyển tài sản:** cấp phát, thu hồi, mượn, trả; bảo trì / sửa chữa; kiểm kê; thanh lý.
- **Tem QR:** in hàng loạt ra PDF. Quét QR bằng điện thoại để mở thông tin tài sản (cần đăng nhập).

## 5.6 Bản quyền phần mềm
- **Giấy phép:** loại, nhà cung cấp, khóa (mã hóa), số lượng, ngày hết hạn.
- Gán cho người hoặc thiết bị; lịch sử gán.
- Nhắc trước khi hết hạn.

## 5.7 Hoàn thiện Phase 5
- Báo cáo nhân sự (giờ công, tăng ca, quỹ lương theo phòng ban).
- Tài liệu, phiên bản 5.0.0.
