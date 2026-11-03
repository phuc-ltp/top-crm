# Nhật ký thay đổi — TOP CRM

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/). Ngày theo giờ Việt Nam.

## [5.8.2] — 03/11/2026

### Sửa lỗi
- `TRUSTED_PROXIES` trong .env không có tác dụng khi chạy qua Apache (được đọc trước khi .env nạp) → đứng sau Cloudflare Tunnel / reverse proxy, đường dẫn vẫn sinh ra `http://` làm camera / định vị trên điện thoại bị chặn. Nay đọc qua `config('app.trusted_proxies')` trong AppServiceProvider.

## [5.8.1] — 03/11/2026

### Cải thiện
- Chấm công → Cài đặt → Thêm/Sửa văn phòng: nút **Lấy vị trí hiện tại** điền sẵn vĩ độ/kinh độ từ GPS của điện thoại (đứng tại văn phòng rồi bấm; cần HTTPS và cho phép định vị).

## [5.8.0] — 03/11/2026

### Thêm mới
- **Link mời đăng ký tài khoản** (Người dùng → Link mời đăng ký, cần quyền tạo người dùng): chọn vai trò, hiệu lực 1–30 ngày, số người tối đa 1–200, ghi chú. Link + mã QR chỉ hiện **một lần** lúc tạo (CSDL chỉ giữ bản băm), thu hồi được bất cứ lúc nào. Không mời được vai trò Chủ sở hữu; Quản trị viên chỉ do chủ sở hữu / quản trị nền tảng mời.
- **Đăng ký bằng điện thoại qua link mời:** điền họ tên, email, số điện thoại, mật khẩu → nhận **mã 6 số qua email** → nhập mã → tài khoản được tạo trong đúng tổ chức, đúng vai trò, có hồ sơ nhân viên (thử việc) và tự đăng nhập. Email đã có tài khoản bị từ chối; link hết hạn / hết lượt / bị thu hồi báo rõ; chống spam (trường ẩn + giới hạn số lần). Người tạo link nhận thông báo khi có người đăng ký.
- **Mã xác thực qua email khi đăng nhập:** mỗi người tự bật ở Hồ sơ → Bảo mật (phải nhập đúng mã gửi tới email mới bật được, tránh tự khóa mình); quản trị nền tảng có thể **bắt buộc cho mọi người** (Cài đặt → Bảo mật đăng nhập — chỉ bật được khi email gửi đi đã hoạt động). Mã hết hạn sau 10 phút, dùng một lần, sai 5 lần là hủy, gửi lại cách nhau 60 giây. Người đã dùng ứng dụng xác thực (TOTP) vẫn dùng ứng dụng.

## [5.7.0] — 29/10/2026

### Thêm mới
- **Cài đặt email ngay trên giao diện** (Hệ thống → Email gửi đi, chỉ quản trị nền tảng): chọn Gmail / Zoho / Outlook / SMTP khác (tự điền máy chủ, cổng, mã hóa + hướng dẫn), mật khẩu lưu mã hóa và không hiển thị lại, nút **Gửi email thử** báo lỗi dễ hiểu (sai mật khẩu, không kết nối, lỗi SSL/TLS). Cấu hình áp dụng ngay cho quên mật khẩu, thông báo, nhắc việc… mà không cần sửa file .env.

## [5.6.1] — 26/10/2026

### Cải thiện
- Danh sách Leads có cột **Liên lạc** (số điện thoại bấm để gọi + biểu tượng mã QR, email bấm để soạn thư) như mẫu; ngày theo dõi tiếp hiển thị gọn dưới giai đoạn.

## [5.6.0] — 26/10/2026

### Cải thiện
- **Thanh toán bằng QR:** nút “Thanh toán bằng QR” trên hóa đơn còn nợ (và bấm vào mã thu nhỏ) mở cửa sổ có hiệu ứng: mã hiện dần, khung quét 4 góc nhấp nháy, tia quét chạy dọc, số tiền đếm tăng dần; sao chép số tài khoản / nội dung / số tiền, tải ảnh QR để gửi khách.
- **Chuyển động toàn hệ thống:** nội dung trượt vào khi mở trang, thẻ hiện lần lượt, cửa sổ phóng mượt với nền mờ, menu thả xuống trượt nhẹ, nút nhấn lún + gợn sóng, thanh tiến độ chạy từ 0, thẻ nhấc nhẹ khi rê chuột. Tự tắt khi thiết bị bật “Giảm chuyển động”. Cửa sổ QR liên hệ có khung quét 4 góc.

### Sửa lỗi
- Cửa sổ (modal) nằm trong vùng có hiệu ứng có thể bị kẹt dưới lớp nền mờ — modal nay luôn được đưa ra lớp trên cùng khi mở.

## [5.5.0] — 26/10/2026

### Thêm mới
- **Mã QR liên hệ:** biểu tượng QR cạnh số điện thoại (danh sách & chi tiết lead, khách hàng, người liên hệ) mở cửa sổ có mã **Gọi điện** (tel:) hoặc **Lưu danh bạ** (vCard: tên, công ty, số, email), nút Gọi và Sao chép số. Mã tạo trên máy chủ, chỉ người được xem bản ghi mới lấy được.
- **VietQR chuyển khoản trên hóa đơn:** chọn ngân hàng nhận tiền trong Cài đặt → hóa đơn chưa thanh toán (web + PDF) có mã VietQR (chuẩn NAPAS 247 / EMVCo, tạo cục bộ) chứa tài khoản, **số tiền còn phải thu** và nội dung là số hóa đơn — khách quét bằng app ngân hàng, tiền vào thẳng tài khoản công ty.

## [5.4.0] — 26/10/2026

### Thêm mới
- **Cài CRM lên điện thoại / máy tính như ứng dụng (PWA):** icon theo logo TOP (kèm bản cho iPhone, Android), mở toàn màn hình, lối tắt Chấm công / Công việc, nút **“Cài ứng dụng lên máy”** trong menu tài khoản (chỉ hiện khi trình duyệt cho phép), trang “Không có kết nối mạng” khi mất mạng. Vì bảo mật, không lưu bất kỳ dữ liệu CRM nào trên thiết bị. Cần HTTPS khi dùng trên điện thoại (sau go-live).

## [5.3.0] — 26/10/2026

### Thêm mới
- **Quản lý hợp đồng:** hợp đồng khách hàng (mã HDG tự sinh; khách hàng, người đại diện, cơ hội; loại, giá trị, ngày ký – hiệu lực – hết hạn, tự gia hạn, điều khoản), tình trạng hiệu lực tự tính (nháp / chưa hiệu lực / đang hiệu lực / sắp hết hạn / đã hết hạn / chấm dứt), **gia hạn** tạo hợp đồng nối tiếp, **chấm dứt** có lý do, tệp hợp đồng đính kèm qua module Tài liệu (bảo mật, phiên bản), nhắc hết hạn 30 / 7 / 0 ngày (`crm:contract-reminders` 08:05), tab Hợp đồng ở trang khách hàng, nút “Tạo hợp đồng” từ cơ hội, xuất Excel, thùng rác, quyền `contracts.*` theo vai trò.

## [5.2.0] — 26/10/2026

### Thêm mới
- **Marketing nâng cao:** ghi nhận chi phí thực tế theo hạng mục (so với ngân sách); cơ hội gắn chiến dịch nguồn (tự động khi lead của chiến dịch chuyển đổi, hoặc chọn trên form cơ hội) → doanh thu đã thắng, giá trị đang theo đuổi, **ROI**; chi phí / lead tính theo chi phí thực tế. Mỗi chiến dịch có **link theo dõi** (form thu lead công khai + mã chiến dịch + UTM): lead đăng ký qua link tự gắn vào chiến dịch, lưu nguồn / kênh. Báo cáo và xuất Excel chiến dịch thêm chi phí thực tế, doanh thu, ROI.

## [5.1.0] — 23/10/2026

### Thêm mới
- **Nghỉ phép & ngày lễ:** đơn nghỉ phép (phép năm, việc riêng có lương, nghỉ ốm, không lương; cả ngày hoặc nửa ngày) tự trừ cuối tuần và ngày lễ, chặn trùng và vượt quỹ phép; quản lý trực tiếp / người quản lý lương duyệt hoặc từ chối có lý do, thông báo hai chiều. Quỹ phép năm mặc định 12 ngày (chỉnh chung và riêng từng người). Ngày lễ theo tổ chức, thêm nhanh ngày lễ dương lịch cố định. Bảng công hiện **P** (nghỉ phép) / **L** (ngày lễ); bảng lương trả giờ nghỉ phép có lương và ngày lễ theo giờ chuẩn (dòng riêng trên phiếu lương, tính vào bảo hiểm và thuế).

## [5.0.3] — 21/10/2026

### Sửa lỗi
- PDF hóa đơn / báo giá / đơn hàng / phiếu lương: khối chữ ký “Khách hàng” bị lệch thấp hơn “Người lập” ~7 mm — hai khối nay căn trên, chia đều 50/50.

## [5.0.2] — 21/10/2026

### Thêm mới
- **Dữ liệu demo cho Phase 4–5:** lệnh `php artisan crm:seed-demo <id tổ chức>` nạp dữ liệu mẫu (khách hàng, bán hàng, dự án, hồ sơ nhân viên, chấm công 2 tháng, tăng ca, bảng lương tháng trước đã duyệt, tài sản, bản quyền phần mềm) vào đúng một tổ chức; `crm:purge-demo` xóa sạch kể cả dữ liệu Phase 5 (thêm cờ `is_demo` cho tài sản, đợt kiểm kê, giấy phép, kỳ lương).

### Sửa lỗi
- Seeder demo không tạo được ghi chú / lịch hẹn khách hàng (cột liên kết không nằm trong danh sách được gán hàng loạt).

## [5.0.1] — 21/10/2026

### Cải thiện
- **PDF nhẹ hơn ~25–30 lần:** mọi PDF (hóa đơn, báo giá, đơn hàng, báo cáo, phiếu lương, tem QR) chỉ nhúng các ký tự font thực sự dùng thay vì cả bộ font tiếng Việt — VD phiếu lương 858 KB → 26 KB, tem QR 8 tài sản 870 KB → 33 KB; hiển thị và dấu tiếng Việt giữ nguyên.

## [5.0.0] — Phase 5 Nhân sự & tài sản — 21/10/2026

### Thêm mới
- **5.1 Tài khoản nhân viên & bậc lương:** tạo nhanh tài khoản từ Danh bạ nhân viên (tên, email, điện thoại, vai trò) với mật khẩu tạm chỉ hiện một lần và bắt buộc đổi ở lần đăng nhập đầu; bảng bậc lương theo giờ (35.000–200.000 đ/giờ, 7 bậc mặc định theo vai trò); bậc / đơn giá riêng trong hồ sơ nhân viên; quyền mới `payroll.view_all`, `payroll.manage`.
- **5.2 Chấm công:** chấm công vào / ra trên điện thoại với ảnh selfie + GPS (giờ lấy theo máy chủ, khoảng cách tới văn phòng tính ở máy chủ, ngoài bán kính bị gắn cờ), nhân viên đồng ý trước khi thu thập; giờ công theo ca 7:30–11:30 & 13:30–17:30 (tối đa 8 giờ), đi trễ / về sớm với số phút cho phép; bảng công tháng; điều chỉnh công có lý do và nhật ký; cài đặt giờ làm, ngày làm việc, văn phòng; tự xóa ảnh selfie quá hạn lưu. `Permissions-Policy` cho phép camera / định vị trên chính tên miền.
- **5.3 Tăng ca:** nhân viên gửi đề nghị tăng ca trong khung 18:00–21:00 (tối đa 3 giờ / ngày, không chồng giờ) kèm mức độ công việc Cao / Vừa / Thấp (100.000 / 70.000 / 40.000 đ/giờ); đơn giá không bao giờ thấp hơn 150% lương giờ của người đó (Điều 98 BLLĐ 2019) và được chốt khi duyệt. Quản lý trực tiếp hoặc người quản lý lương duyệt / từ chối (bắt buộc ghi lý do), có thông báo hai chiều; tổng giờ và tiền tăng ca đã duyệt theo tháng.
- **5.4 Bảng lương:** kỳ lương theo tháng (nháp → chờ duyệt → đã duyệt → đã trả) tính tự động từ bảng công và tăng ca đã duyệt; phụ cấp / thưởng / khấu trừ nhập tay; BHXH 8%, BHYT 1,5%, BHTN 1% (có trần) và thuế TNCN lũy tiến với giảm trừ bản thân / người phụ thuộc, phần chênh tăng ca được miễn thuế — bật / tắt và chỉnh mức trong cài đặt. Kỳ đã duyệt bị khóa; nhân viên nhận thông báo và xem / tải PDF phiếu lương của mình; xuất Excel bảng lương. Hồ sơ nhân viên có thêm số người phụ thuộc.
- **5.5 Quản lý tài sản:** tổng quan (số lượng, nguyên giá, giá trị còn lại theo khấu hao đường thẳng, theo trạng thái / nhóm, mượn quá hạn, sắp hết bảo hành, chi phí bảo trì); danh mục tài sản có mã tự sinh, nhóm với số tháng khấu hao mặc định, nhà cung cấp, chi nhánh, vị trí, bảo hành; cấp phát / thu hồi / cho mượn có hạn trả / bảo trì / đổi tình trạng / thanh lý với lịch sử luân chuyển không sửa được; in tem QR hàng loạt ra PDF, quét QR bằng camera điện thoại để mở tài sản; kiểm kê theo đợt; nhân viên xem tài sản mình đang giữ; xuất Excel.
- **5.6 Bản quyền phần mềm:** quản lý giấy phép (loại, nhà phát hành, số chỗ, hạn dùng, chi phí theo chu kỳ, người phụ trách) với khóa bản quyền mã hóa — chỉ người quản lý xem được và mỗi lần xem được ghi nhật ký; gán cho người hoặc thiết bị không vượt số chỗ, có lịch sử gán / thu hồi; tự nhắc trước 30 ngày, 7 ngày và đúng ngày hết hạn; nhắc tài sản mượn quá hạn hằng ngày (lệnh `crm:asset-reminders` lúc 08:00).
- **5.7 Báo cáo:** giờ công & tăng ca theo nhân viên, quỹ lương theo phòng ban (kỳ đã duyệt / đã trả), giá trị tài sản theo nhóm — xem trên web, xuất Excel / PDF; chỉ người có quyền lương / tài sản mới thấy.

## [4.0.0] — Phase 4 Vận hành đội nhóm — 21/10/2026

- **4.6 Hoàn thiện:** tìm kiếm toàn cục gồm dự án và tài liệu (theo đúng quyền xem / thành viên dự án).

### Thêm mới
- **Thông báo:** 7 nhóm, trang thông báo mới, chuông tự cập nhật, tùy chọn nhận theo từng người (ứng dụng / email), thông báo có thanh toán và hóa đơn đã thu đủ, tự dọn thông báo cũ.
- **4.1 Dự án & công việc nâng cao:** dự án (thành viên, tiến độ, khách hàng, ngân sách), việc con một cấp, phụ thuộc giữa công việc (chặn vòng lặp, chặn hoàn thành khi việc trước chưa xong), bảng Kanban kéo thả, trang khối lượng công việc, lọc công việc theo dự án, xuất Excel dự án. Quyền mới `projects.*`.
- **4.2 Nhân viên & nhóm:** danh bạ nhân viên, hồ sơ (thông tin công việc + thông tin cá nhân mã hóa, quyền xem riêng), sơ đồ tổ chức (chặn vòng lặp quản lý), nhóm (trưởng nhóm, thành viên), chi nhánh, KPI cá nhân / nhóm từ dữ liệu thật. Quyền mới `employees.*`, `teams.*`.
- **4.5 Quản lý tài liệu:** thư viện tài liệu có danh mục, phạm vi xem (riêng tư / theo nhóm / toàn công ty), liên kết khách hàng / cơ hội / dự án, lịch sử phiên bản, xem trước PDF / ảnh, tải xuống có kiểm tra quyền và ghi nhật ký, kiểm tra loại tệp theo nội dung (finfo), mã SHA-256. Quyền mới `documents.*`; tài liệu đã xóa khôi phục được ở Thùng rác. Bỏ qua thông báo rỗng trên giao diện.
- **4.4 Lịch & lịch hẹn:** giờ kết thúc, địa điểm / link họp, người tham dự nội bộ (mời, nhắc lịch), cảnh báo trùng lịch, trang Lịch chung (tháng / tuần, gồm hạn công việc), xuất tệp .ics.
- **4.3 SLA hỗ trợ khách hàng:** hạn phản hồi đầu / xử lý theo mức ưu tiên (cài đặt từng tổ chức), tạm dừng khi chờ khách, cảnh báo và leo thang tự động (`crm:ticket-sla` mỗi 10 phút), danh mục ticket, lịch sử trạng thái, đánh giá hài lòng (CSAT), trang phân tích hỗ trợ.

- **Sẵn sàng production:** Content-Security-Policy và HSTS ở môi trường production, ép HTTPS cho URL, hỗ trợ reverse proxy (`TRUSTED_PROXIES`), lệnh `crm:production-check` kiểm tra trước go-live, `.env.production.example`, `scripts/deploy.sh`, `scripts/backup.sh`, cấu hình Nginx mẫu, hướng dẫn `docs/GO_LIVE.md`. `crm:purge-demo` xóa cả dự án demo và thông báo của tài khoản demo.

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
