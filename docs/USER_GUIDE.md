# Hướng dẫn sử dụng TOP CRM

## 1. Mở hệ thống và đăng nhập

1. Bấm đúp **`START_CRM.bat`**, trình duyệt sẽ tự mở.
2. Nhập **Email hoặc tên đăng nhập** và **Mật khẩu** do quản trị viên cấp (bấm biểu tượng 👁 để xem lại mật khẩu đã gõ). Nếu quản trị viên đã bật, bạn có thể bấm **Google** hoặc **Facebook** để đăng nhập bằng tài khoản có cùng email. Tích **Ghi nhớ đăng nhập** nếu dùng máy riêng.
3. Nếu nhập sai mật khẩu 5 lần, tài khoản bị tạm khóa 5 phút để chống dò mật khẩu.
4. Quên mật khẩu: bấm **Quên mật khẩu?** hoặc nhờ quản trị viên đặt lại.

**Lần đầu tiên** sử dụng hệ thống, trình duyệt mở trang *Tạo tài khoản quản trị*. Hãy nhập họ tên, email và một mật khẩu mạnh (tối thiểu 10 ký tự, có chữ hoa, chữ thường và số).

## 2. Giao diện

- **Menu bên trái:** các chức năng. Bấm ☰ để thu gọn hoặc mở rộng. Trên điện thoại, menu ẩn đi và mở bằng nút ☰.
- **Thanh tìm kiếm** (trên cùng): gõ ít nhất 2 ký tự để tìm khách hàng, lead, cơ hội, hóa đơn, ticket.
- **Nút "Tạo nhanh"** màu cam: tạo khách hàng, lead, công việc... ngay từ bất kỳ trang nào.
- **Chuông 🔔:** thông báo nội bộ, ví dụ được giao việc, việc sắp đến hạn, deal đổi giai đoạn.
- **Ảnh đại diện:** hồ sơ cá nhân, đổi mật khẩu, xem các thiết bị đang đăng nhập, đăng xuất.

Bạn chỉ thấy menu và dữ liệu phù hợp với vai trò của mình. Ví dụ nhân viên kinh doanh chỉ thấy khách hàng mình phụ trách.

## 3. Bảng điều khiển (Dashboard)

Trang Tổng quan có 4 thẻ chính (doanh thu, khách hàng mới, cơ hội mới, hóa đơn) và các biểu đồ. Bên dưới là khung **Chỉ số điều hành** gồm 20 chỉ số, chia 4 nhóm:
- Khách hàng & Lead
- Bán hàng
- Tài chính
- Đội ngũ & vận hành

Đổi khoảng thời gian ở góc trên bên phải: Hôm nay, Hôm qua, 7 ngày qua, 30 ngày qua, Tháng này, Tháng trước, Quý này, Năm nay, Tùy chọn.

- **Xem công thức:** bấm biểu tượng ⓘ cạnh mỗi chỉ số. Danh sách đầy đủ có trong `docs/DASHBOARD_METRICS.md`.
- **"hiện tại":** chỉ số gắn nhãn này là trạng thái ngay lúc xem, không phụ thuộc kỳ đã chọn.
- **"—":** chưa có dữ liệu để tính. Lý do ghi ngay bên dưới.
- **DEMO:** nhóm chỉ số có tính cả dữ liệu demo.
- **Phạm vi:** mỗi người chỉ thấy số liệu trong phạm vi mình được xem.

**Doanh thu** = số tiền khách **đã thanh toán thực tế**, tính theo ngày thanh toán.

## 4. Khách hàng

**Thêm khách hàng:** vào *Khách hàng*, bấm **Thêm khách hàng**, điền thông tin rồi bấm **Lưu**.
- Trường có dấu `*` là bắt buộc.
- Nếu email hoặc số điện thoại trùng với khách đã có, hệ thống cảnh báo. Kiểm tra lại, hoặc tích *"Tôi đã kiểm tra — vẫn lưu"*.

**Tìm và lọc:** dùng ô tìm kiếm và các bộ lọc (trạng thái, loại, nguồn, nhân viên, thẻ). Bấm tiêu đề cột để sắp xếp.

**Chi tiết khách hàng:** xem người liên hệ, cơ hội, hóa đơn, ticket, công việc và **lịch sử tương tác**. Ghi lại cuộc gọi, email, cuộc họp ở ô *Lịch sử tương tác*.

**Lưu trữ:** bấm 🗑 để lưu trữ (xóa mềm). Xem lại bằng bộ lọc *Đã lưu trữ*, bấm **Khôi phục** nếu cần.

**Nhập từ Excel/CSV:** bấm **Nhập CSV**, rồi **Tải tệp mẫu**. Điền tệp mẫu bằng Excel, lưu dạng *CSV UTF-8*, rồi tải lên.

**Xuất:** bấm **Xuất**, chọn **Excel** hoặc **CSV**. Tệp xuất ra theo đúng bộ lọc đang chọn.

**Phân công hàng loạt** (Quản lý): tích chọn nhiều khách hàng, chọn nhân viên, bấm **Phân công**.

## 5. Leads (khách hàng tiềm năng)

1. *Leads*, bấm **Tạo lead**, nhập thông tin, nguồn, giá trị ước tính.
2. **Chấm điểm tự động** (0–100): Nóng ≥ 70, Ấm 40–69, Lạnh < 40. Điểm dựa trên độ đầy đủ thông tin, giá trị, giai đoạn, nguồn giới thiệu và số lần tương tác.
3. **Cập nhật giai đoạn:** các giai đoạn do tổ chức tự cấu hình ở *Hệ thống › Cấu hình lead*. Khi chuyển sang *Thất bại* phải chọn lý do. Có thể hẹn ngày theo dõi tiếp; hệ thống sẽ nhắc.
4. **Chuyển đổi:** khi khách đồng ý, bấm **Chuyển đổi** để tạo khách hàng mới (hoặc gắn vào khách đã có) và tạo cơ hội trong Pipeline.
5. **Nhập từ tệp:** bấm **Nhập CSV / Excel** rồi **Tải tệp mẫu**. Dòng trùng email hoặc số điện thoại với lead hay khách hàng đã có sẽ bị bỏ qua, và mỗi dòng bị bỏ qua đều được báo kèm số dòng.
6. **Form thu lead từ website** (*Cấu hình lead*): lead điền form trên website sẽ tự vào hệ thống. Trang *Phân tích* xem lead theo nguồn, giai đoạn và tỷ lệ chuyển đổi.

## 6. Pipeline bán hàng

- Mỗi cột là một giai đoạn: Cơ hội mới → Đánh giá nhu cầu → Gửi đề xuất → Đàm phán → Thắng / Thất bại.
- **Kéo thả thẻ** sang cột khác để đổi giai đoạn. Hệ thống tự lưu, cập nhật xác suất và ghi lịch sử.
- Đầu mỗi cột hiện tổng giá trị. Bấm vào thẻ để xem chi tiết, tạo báo giá hoặc công việc.
- **Nhiều pipeline:** mỗi tổ chức có thể có nhiều pipeline (*Hệ thống › Cấu hình pipeline*). Chọn pipeline bằng các tab phía trên Kanban.
- **Thất bại:** khi kéo thẻ vào cột *Thất bại*, phải chọn lý do.
- **Dự báo** (*Cơ hội › Dự báo*): giá trị dự kiến theo tháng = giá trị × xác suất, so với chỉ tiêu.

## 7. Bán hàng: Báo giá → Đơn hàng → Hóa đơn → Thanh toán

1. **Báo giá:** *Báo giá*, bấm **Tạo báo giá**, chọn khách hàng, thêm dòng sản phẩm (bấm *Thêm dòng*), nhập chiết khấu và VAT. Bấm **Tải PDF** để gửi khách.
2. **Đơn hàng:** trong báo giá, bấm **Tạo đơn hàng**. Quản lý bấm **Duyệt đơn**.
3. **Hóa đơn:** trong đơn hàng đã duyệt, bấm **Lập hóa đơn** (hóa đơn nháp). Kiểm tra lại rồi bấm **Phát hành**.
4. **Thanh toán:** trong hóa đơn đã phát hành, nhập số tiền khách trả rồi bấm **Ghi nhận**. Trạng thái tự chuyển: Chưa thanh toán → Thanh toán một phần → Đã thanh toán.

> Mọi số tiền (thành tiền, thuế, tổng) đều do **hệ thống tự tính** khi lưu. Hóa đơn đã phát hành thì không sửa được nữa, chỉ có thể hủy (khi chưa có thanh toán).

**Chỉ tiêu doanh số:** Quản lý đặt chỉ tiêu tháng cho từng nhân viên. Hệ thống tự so sánh với tiền đã thu.

## 8. Công việc

- **Tạo công việc:** tiêu đề, hạn chót, mức ưu tiên, người thực hiện, liên quan tới khách hàng/lead/cơ hội nào, checklist.
- Trong chi tiết công việc: tích checklist, bình luận, đính kèm tệp (PDF, Word, Excel, ảnh, tối đa 10MB).
- **Lịch công việc:** xem theo tháng.
- Hệ thống tự gửi thông báo khi việc sắp đến hạn (trong 24 giờ) và tự chuyển sang *Quá hạn* khi trễ.

## 9. Sản phẩm, Marketing, Hỗ trợ

- **Sản phẩm:** SKU, tên, danh mục, giá, ảnh. Dùng để chọn nhanh khi lập báo giá. Nhập hàng loạt bằng **Nhập CSV / Excel**; SKU đã có sẽ được bỏ qua, không ghi đè sản phẩm cũ.
- **Người liên hệ:** có thể nhập từ tệp. Để gắn người liên hệ với khách hàng, điền mã khách hàng (VD: KH00012) vào cột `customer`.
- **Công việc:** bấm **Xuất Excel** để tải danh sách theo bộ lọc đang chọn.
- **Marketing:** tạo chiến dịch (ngân sách, lead mục tiêu), gắn lead vào chiến dịch, xem tỷ lệ chuyển đổi và chi phí mỗi lead. Hệ thống **không tự gửi email hàng loạt**.
- **Hỗ trợ khách hàng:** tạo ticket, phản hồi, ghi chú nội bộ (khách không thấy), đổi trạng thái Mới mở → Đang xử lý → Chờ phản hồi → Đã giải quyết → Đã đóng.

## 10. Báo cáo

*Báo cáo*: chọn loại báo cáo, lọc theo ngày, nhân viên, trạng thái, rồi bấm **Xuất báo cáo** để tải Excel, CSV hoặc PDF. Mỗi báo cáo ghi rõ **định nghĩa** cách tính chỉ số.

## 11. Tìm kiếm toàn cục

Ô tìm kiếm trên thanh trên cùng (phím tắt **Ctrl + K**; trên điện thoại bấm biểu tượng kính lúp) tìm cùng lúc trong 11 loại dữ liệu:

| Nhóm | Loại dữ liệu |
|---|---|
| Khách hàng & bán hàng | khách hàng, người liên hệ, lead, cơ hội, báo giá, đơn hàng, hóa đơn |
| Danh mục & vận hành | sản phẩm, ticket, công việc |
| Nội bộ | nhân viên |

- **Chọn kết quả:** gõ ít nhất 2 ký tự, chọn bằng phím mũi tên và Enter. Bấm **Xem tất cả kết quả** để mở trang kết quả đầy đủ, có lọc theo loại.
- **Số điện thoại:** viết kiểu nào cũng tìm được (0901 234 567, 0901.234.567, +84 901…).
- **Tìm gần đây:** bấm vào ô khi ô đang trống để xem 10 lần tìm mới nhất của bạn. Chỉ bạn thấy danh sách này và có thể xóa nó.
- **Phạm vi:** chỉ hiện kết quả bạn được phép xem.

## 12. Thùng rác

Bản ghi bị xóa không mất hẳn, mà được chuyển vào *Hệ thống › Thùng rác*. Bạn sẽ thấy ở đó:
- bản ghi đã xóa của các loại dữ liệu mà bạn có quyền xóa;
- ngày xóa và người xóa.

Bấm **Khôi phục** để đưa bản ghi trở lại. Việc khôi phục được ghi vào nhật ký hoạt động. Hệ thống không có chức năng xóa vĩnh viễn.

## 13. Kho hàng, nhà cung cấp, bảng giá (Phase 3)

**Nhà cung cấp** (*Sản phẩm & Dịch vụ › Nhà cung cấp*): thêm nhà cung cấp, rồi gắn vào sản phẩm.

**Sản phẩm:**
- Chọn loại *Sản phẩm* (có tồn kho) hoặc *Dịch vụ* (không theo dõi tồn kho).
- Nhập giá vốn (chỉ người được sửa sản phẩm thấy) và mức tồn tối thiểu để nhận cảnh báo tồn thấp.
- Muốn có màu / size, bấm **Thêm biến thể** trên trang sản phẩm.

**Bảng giá** (*Sản phẩm & Dịch vụ › Bảng giá*): tạo bảng giá (VD: Đại lý) rồi gắn vào khách hàng ở trang sửa khách hàng. Khi lập báo giá / đơn / hóa đơn cho khách đó, giá được điền sẵn.

**Kho hàng** (*Kho hàng*):
- **Tồn kho:** xem tồn thực, đang giữ, có thể dùng.
- **Phiếu kho:**
  - **Nhập kho:** từ nhà cung cấp, có đơn giá nhập.
  - **Xuất kho.**
  - **Kiểm kê:** nhập số đếm thực tế, hệ thống tự tính chênh lệch.
  - **Chuyển kho.**
  - Phiếu ghi sổ ngay và **không sửa / xóa được**; có sai sót thì lập phiếu kiểm kê để điều chỉnh.
- **Sổ kho:** xem mọi phát sinh.
- **Không xuất quá tồn.** Muốn cho phép tồn âm thì bật trong *Cài đặt › Kho hàng*.

**Đơn hàng giữ hàng & xuất kho tự động** (bật trong *Cài đặt › Kho hàng* khi đã nhập tồn kho):
- Duyệt đơn: giữ hàng. Thiếu hàng thì không duyệt được.
- Đơn chuyển "Hoàn thành": tự lập phiếu xuất kho.
- Hủy đơn: trả phần đang giữ.
- Khách trả hàng: mở đơn và dùng mục **Trả hàng (nhập lại kho)**.

## 14. Báo giá: duyệt chiết khấu và mẫu

**Duyệt chiết khấu:**
- Trong *Cài đặt › Báo giá*, đặt **ngưỡng chiết khấu cần duyệt (%)**.
- Báo giá có tổng chiết khấu vượt ngưỡng do nhân viên lập sẽ "Chờ duyệt": giữ ở Nháp, chưa tạo đơn hàng được.
- Quản lý nhận thông báo, rồi bấm **Duyệt** hoặc **Từ chối** (ghi lý do) trên trang báo giá.

**Mẫu báo giá:** trên một báo giá chuẩn, quản lý bấm **Lưu làm mẫu**. Khi lập báo giá mới, chọn mẫu ở ô **Dùng mẫu báo giá**.

**Hết hạn:** báo giá quá ngày hiệu lực tự chuyển "Hết hạn".

## 15. Hóa đơn: thanh toán, hoàn tiền, giảm trừ, đối soát

Trên hóa đơn đã phát hành:
- **Ghi nhận thanh toán.**
- Mục **Hoàn tiền / phiếu giảm trừ:**
  - hoàn tiền (bắt buộc ghi lý do) làm giảm số đã thu và doanh thu;
  - phiếu giảm trừ làm giảm số còn phải thu.

**Còn phải thu** = tổng − giảm trừ − đã thu.

**Đối soát thanh toán** (*Bán hàng › Đối soát thanh toán*, dành cho kế toán):
- Tích các khoản đã khớp sao kê ngân hàng rồi bấm **Đánh dấu đã đối soát**.
- Khoản đã đối soát không xóa được.

**Thuế suất:** trong *Cài đặt › Báo giá*, nhập các mức VAT được dùng (VD: `0, 5, 8, 10`). Ô thuế trên chứng từ sẽ thành danh sách chọn.

> Hóa đơn trong CRM là **chứng từ nội bộ** để theo dõi công nợ, không phải hóa đơn điện tử hợp lệ theo quy định thuế.

**Tác vụ tự động** (nhắc việc, báo giá hết hạn, nhắc hóa đơn sắp đến hạn / quá hạn, cảnh báo tồn thấp) chỉ chạy khi đã mở **`START_CRM.bat`**. Hãy chạy file này mỗi lần bật máy, kể cả khi dùng qua XAMPP.

## 16a. Dự án và công việc nâng cao (Phase 4)

- **Dự án** (menu Công việc › Dự án): tạo dự án, chọn người quản lý, thành viên, khách hàng (nếu có), thời gian và ngân sách tham khảo. Tiến độ tự tính theo số việc đã hoàn thành (không tính việc đã hủy). Thành viên xem được mọi công việc của dự án; chỉ người quản lý hoặc người tạo được sửa dự án.
- **Việc con:** ở trang chi tiết công việc bấm **Thêm việc con**. Việc con luôn thuộc cùng dự án với việc cha, chỉ có một cấp.
- **Phụ thuộc:** ở trang chi tiết công việc, mục **Phụ thuộc**, chọn việc phải xong trước. Hệ thống không cho tạo vòng lặp và không cho đánh dấu hoàn thành khi việc phải xong trước chưa xong.
- **Bảng Kanban** (Công việc › Bảng Kanban): kéo thẻ sang cột khác để đổi trạng thái; thẻ không có quyền sửa thì không kéo được.
- **Khối lượng công việc:** xem mỗi người đang giữ bao nhiêu việc, trễ bao nhiêu, đến hạn 7 ngày tới, tổng giờ ước tính. Nhân viên chỉ thấy dòng của mình.

## 16b. Nhân viên, nhóm, chi nhánh (Phase 4)

- **Danh bạ nhân viên** (Quản trị › Nhân sự): tìm theo tên, email, chức danh, mã NV; lọc theo phòng ban, nhóm, chi nhánh, trạng thái.
- **Hồ sơ nhân viên:** thông tin công việc, nhóm, cấp dưới trực tiếp và **KPI** (tháng / quý / năm): doanh thu đã thu so với chỉ tiêu, cơ hội thắng, việc hoàn thành và tỉ lệ đúng hạn, ticket đã giải quyết.
- **Thông tin cá nhân** (ngày sinh, CCCD, địa chỉ...): chỉ chủ sở hữu / quản trị thấy và sửa; dữ liệu được mã hóa.
- **Sơ đồ tổ chức:** vẽ theo quản lý trực tiếp; hệ thống không cho chọn quản lý tạo vòng lặp.
- **Nhóm:** tạo nhóm, chọn trưởng nhóm và thành viên; trang nhóm hiện KPI cả nhóm và từng người.
- **Chi nhánh:** thêm / sửa / ngừng hoạt động; không xóa được chi nhánh còn nhân viên.

## 16c. SLA hỗ trợ khách hàng (Phase 4)

- **Hạn SLA** tự đặt khi tạo ticket, theo mức ưu tiên: hạn phản hồi đầu và hạn xử lý xong. Chỉnh số giờ ở **Cài đặt › SLA hỗ trợ khách hàng**.
- **Phản hồi đầu** chỉ tính phản hồi gửi khách; ghi chú nội bộ không tính.
- Khi chuyển ticket sang **Chờ phản hồi** (chờ khách), đồng hồ SLA tạm dừng; tiếp tục xử lý thì hạn được dời đúng bằng thời gian đã chờ.
- **Leo thang tự động** (mỗi 10 phút): còn dưới 1 giờ thì nhắc người phụ trách; quá hạn thì báo quản lý trực tiếp và quản lý CSKH. Mỗi mức chỉ báo một lần.
- Danh sách ticket có thẻ **Vi phạm SLA** và cột SLA; trang chi tiết có bảng SLA, **lịch sử trạng thái**, **đánh giá hài lòng** (1–5 sao, nhập sau khi đã giải quyết).
- **Phân tích hỗ trợ:** tỉ lệ đạt SLA, thời gian phản hồi / xử lý trung bình, CSAT, theo danh mục và nhân viên. **Danh mục ticket** quản lý ở menu Hỗ trợ khách hàng.

## 16d. Lịch chung và lịch hẹn (Phase 4)

- **Lên lịch** ở trang khách hàng / lead / cơ hội (mục Hoạt động › Lên lịch): chọn giờ bắt đầu, giờ kết thúc, địa điểm hoặc link họp, người phụ trách và **người tham dự** nội bộ.
- Người tham dự được mời (thông báo), được nhắc trước giờ hẹn và thấy lịch hẹn trên lịch của mình.
- **Cảnh báo trùng lịch:** khi người phụ trách hoặc người tham dự đã có lịch khác trùng giờ, hệ thống báo ngay sau khi lưu (vẫn cho lưu).
- **Lịch chung** (Công việc › Lịch chung): xem theo tháng hoặc tuần, gồm lịch hẹn và hạn công việc. Quản lý xem được lịch của nhân viên.
- Nút **.ics** tải tệp lịch để thêm vào Google Calendar hoặc Outlook.

## 16e. Tài liệu (Phase 4)

- **Tài liệu** (menu Vận hành): tải lên hợp đồng, báo giá, quy định...; chọn danh mục, liên kết khách hàng / cơ hội / dự án.
- **Phạm vi xem:** Riêng tư (chỉ mình bạn), Theo nhóm (thành viên một nhóm bạn tham gia), Toàn công ty.
- **Phiên bản:** tải bản mới ở trang chi tiết; bản cũ vẫn giữ và tải lại được.
- **Xem trước** PDF / ảnh ngay trên trình duyệt; loại khác thì tải về. Mỗi lượt xem / tải được ghi lại.
- An toàn: tệp không có đường dẫn công khai; loại tệp kiểm tra theo nội dung thật (tệp đổi đuôi giả mạo bị từ chối); tối đa 20 MB mỗi tệp.

## 16f. Tài khoản nhân viên và bậc lương (Phase 5)

- **Thêm nhân viên** (Danh bạ nhân viên › Thêm nhân viên): nhập họ tên, email, điện thoại, vai trò. Hệ thống tạo mật khẩu tạm và **chỉ hiện một lần**. Hãy chép và gửi cho nhân viên qua kênh an toàn. Nhân viên phải đổi mật khẩu ở lần đăng nhập đầu.
- **Bậc lương** (Quản trị › Nhân sự › Bậc lương): đơn giá theo giờ từ 35.000 đến 200.000 đ. Mỗi vai trò có một bậc mặc định; chỉnh bậc hoặc đơn giá riêng trong hồ sơ từng nhân viên.
- Lương theo giờ chỉ hiện cho chính nhân viên đó và người có quyền lương.

## 16g. Chấm công (Phase 5)

- **Chấm công của tôi** (menu Chấm công), mở trên điện thoại:
  - lần đầu tích đồng ý cho hệ thống chụp ảnh và lấy vị trí;
  - bấm **Chấm công vào** khi đến và **Chấm công ra** khi về.
  - Cần mở bằng địa chỉ **https** và cho phép camera, vị trí. Nếu camera không mở được, dùng nút chụp ảnh bằng máy.
- **Giờ công** chỉ tính trong giờ làm (mặc định 7:30–11:30 và 13:30–17:30). Trễ trong số phút cho phép không bị tính trễ.
- **Bảng công tháng** (quản lý / kế toán): ký hiệu từng ngày. Dấu ! là chấm công ngoài khu vực văn phòng; bấm vào ô để xem ảnh, vị trí, điều chỉnh (bắt buộc ghi lý do).
- **Cài đặt chấm công:** giờ làm, khung tăng ca, ngày làm việc, phút cho phép trễ, số ngày lưu ảnh, văn phòng (tọa độ lấy từ Google Maps, bán kính 100–200 m).

## 16. Đổi mật khẩu và hồ sơ

Bấm ảnh đại diện, chọn **Hồ sơ cá nhân** để đổi tên, số điện thoại, ảnh đại diện và mật khẩu (tối thiểu 10 ký tự, có chữ hoa, chữ thường và chữ số). Sau khi đổi mật khẩu, các thiết bị khác đang đăng nhập sẽ tự bị đăng xuất.

**Bảo mật tài khoản** (ảnh đại diện → Bảo mật tài khoản):
- **Xác thực 2 lớp:** nhập mật khẩu → quét mã QR bằng Google Authenticator / Microsoft Authenticator → nhập mã 6 số. Lưu 8 mã khôi phục ở nơi an toàn (dùng khi mất điện thoại). Từ đó mỗi lần đăng nhập cần thêm mã 6 số.
- **Lịch sử đăng nhập:** xem các lần đăng nhập đúng/sai gần đây. Thấy lạ thì đổi mật khẩu và đăng xuất các thiết bị khác ở **Phiên đăng nhập**.
- **Xác minh email:** bấm Gửi email xác minh.

**Giao diện:** nút mặt trăng/mặt trời trên thanh trên cùng để đổi sáng/tối (lưu theo tài khoản); ô **Tìm chức năng...** ở menu trái để tìm nhanh trang cần mở; chọn ngôn ngữ Việt/Anh ở góc trên phải. Thuộc nhiều tổ chức thì chuyển ở ô **Không gian làm việc** góc dưới menu trái.

## 17. Tắt hệ thống

Bấm đúp **`STOP_CRM.bat`**. Dữ liệu đã lưu trong MySQL nên không bị mất.
