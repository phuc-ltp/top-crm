# Phase 3 — Vận hành bán hàng: kế hoạch & tiêu chí nghiệm thu

*Căn cứ: Master Prompt TOP CRM (14/10/2026) — Phase 3 "Sales Operations", Module 05 (Sản phẩm & dịch vụ), 06 (Báo giá & đơn hàng), 07 (Hóa đơn & thanh toán), 08 (Kho hàng). Bắt đầu 16/10/2026.*

## Nguyên tắc
Giữ nguyên các nguyên tắc của Phase 1–2:
- Làm từng bước trong worktree.
- Migration chỉ thêm và đảo ngược được, thử trên bản sao database thật.
- Sao lưu trước khi chạy database thật.
- Có test về quyền và cách ly tổ chức.
- Song ngữ Việt / Anh.
- Không có nút không hoạt động, không có số liệu giả.

Riêng Phase 3:
- **Tiền và số lượng tính bằng số thập phân chính xác** (bcmath). Không dùng số thực dấu phẩy động cho tiền.
- **Sổ kho là nhật ký bất biến.** Mọi thay đổi tồn kho là một dòng phát sinh; không sửa hay xóa phát sinh cũ, sai thì lập phiếu điều chỉnh.
- **Chống sai lệch tồn kho khi nhiều người thao tác cùng lúc:** cập nhật trong giao dịch, khóa dòng tồn kho.
- **Chứng từ nội bộ:** hóa đơn trong CRM là chứng từ nội bộ, **không phải hóa đơn điện tử hợp lệ theo luật Việt Nam**, và giao diện ghi rõ điều đó. Chưa tích hợp cổng thanh toán hay nhà cung cấp hóa đơn điện tử khi chưa có thông tin nhà cung cấp.

## Quyết định mặc định (có thể đổi nếu bạn muốn)
- **Tồn kho âm:** mặc định **cấm**. Mỗi tổ chức có thể cho phép trong Cài đặt.
- **Giá vốn:** **bình quân gia quyền**, cập nhật mỗi lần nhập kho.
- **Biến thể sản phẩm** (màu, kích cỡ…): mỗi biến thể là một sản phẩm con có SKU, giá và tồn kho riêng, gắn với sản phẩm cha.
- **Dịch vụ:** không theo dõi tồn kho.

## 3.1 Nền tiền tệ chính xác & danh mục sản phẩm nâng cao
- `App\Support\Decimal` (bcmath). Tính tiền chứng từ chuyển sang số thập phân chính xác, kết quả không đổi với dữ liệu cũ.
- Sản phẩm có thêm:
  - loại (sản phẩm / dịch vụ);
  - giá vốn;
  - thuế suất riêng (để trống thì dùng mặc định của tổ chức);
  - nhà cung cấp;
  - mức tồn tối thiểu;
  - biến thể (sản phẩm cha / con);
  - trang lịch sử thay đổi (giá, trạng thái…).
- Bảng **nhà cung cấp** (danh sách, tạo, xem, sửa, xóa, tìm, xuất) và quyền mới `suppliers.*`.
- **Bảng giá:** giá riêng theo nhóm khách hàng, gắn vào khách hàng. Áp dụng ở máy chủ khi thêm sản phẩm vào chứng từ.
- **Nghiệm thu:**
  - tổng tiền chứng từ cũ tính lại không đổi;
  - giá theo bảng giá được áp ở máy chủ (trình duyệt gửi giá khác cũng không có tác dụng nếu người dùng không có quyền sửa giá);
  - nhà cung cấp và bảng giá của tổ chức A không thấy ở tổ chức B.

## 3.2 Kho hàng
- Nhiều kho.
- Tồn kho theo sản phẩm × kho: có thể dùng, đang giữ.
- **Sổ kho (stock_movements)**, có 4 loại phiếu:
  - **phiếu nhập:** từ nhà cung cấp, có giá vốn;
  - **phiếu xuất;**
  - **phiếu điều chỉnh:** kiểm kê;
  - **phiếu chuyển kho.**
- Định giá tồn kho (số lượng × giá vốn bình quân).
- Cảnh báo tồn thấp: thông báo cho người quản lý kho.
- Quyền mới `inventory.*`, gán sẵn cho vai trò Quản lý kho.
- **Nghiệm thu:**
  - không xuất quá tồn khi tổ chức cấm tồn âm;
  - 2 yêu cầu xuất đồng thời không làm tồn sai;
  - sổ kho khớp tồn kho;
  - giá vốn bình quân đúng công thức.

## 3.3 Đơn hàng ↔ kho
- **Giữ hàng** khi duyệt đơn.
- **Xuất kho** khi giao hoặc hoàn thành.
- **Hủy đơn** thì trả lại phần đang giữ.
- **Trả hàng:** nhập lại kho, ghi nhận trên đơn.
- Lịch sử thay đổi dòng đơn hàng.
- **Nghiệm thu:** tồn "có thể dùng" = tồn thực − đang giữ; luồng duyệt → giao → trả hàng khớp sổ kho.

## 3.4 Báo giá
- Mẫu báo giá: dòng sản phẩm và điều khoản soạn sẵn.
- Quy trình duyệt: chiết khấu vượt ngưỡng của tổ chức thì phải được quản lý duyệt trước khi gửi.
- Tự chuyển sang "Hết hạn" khi quá hạn hiệu lực.
- **Nghiệm thu:** nhân viên không gửi được báo giá vượt ngưỡng khi chưa được duyệt; người duyệt có ghi nhận trong nhật ký.

## 3.5 Hóa đơn & thanh toán
- Thuế suất cấu hình theo tổ chức.
- **Phiếu giảm trừ** (credit note) và **hoàn tiền**, tác động đúng số còn phải thu.
- **Nhắc thanh toán:**
  - thông báo khi hóa đơn sắp và đã quá hạn;
  - gửi email khi đã cấu hình SMTP.
- **Đối soát thanh toán:** đánh dấu đã đối soát, kèm mã giao dịch ngân hàng.
- Nhật ký tài chính.
- **Nghiệm thu:** số còn phải thu = tổng − đã thu − giảm trừ + hoàn tiền; không hoàn quá số đã thu; mọi thao tác tài chính có nhật ký.

## 3.6 Báo cáo & hoàn thiện Phase 3
- Báo cáo sản phẩm, tồn kho, nhập–xuất–tồn, đơn hàng, công nợ.
- Rà checklist từng module; cập nhật dữ liệu demo, tài liệu, file SQL; đánh dấu **v3.0.0**.
