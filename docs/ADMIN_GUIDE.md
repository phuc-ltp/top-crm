# Hướng dẫn quản trị TOP CRM

## 1. Tài khoản quản trị đầu tiên

- Khi hệ thống chưa được khởi tạo, mọi truy cập đều được chuyển tới trang **/setup** để bạn tự tạo tài khoản (tự đặt mật khẩu). Sau khi tạo xong, trang này **khóa vĩnh viễn**, kể cả khi tài khoản quản trị về sau bị xóa (chống người lạ chiếm quyền). Dù có nhiều yêu cầu gửi cùng lúc thì cũng chỉ một tài khoản được tạo.
- Cách khác: mở Command Prompt tại `C:\phuc-crm` và chạy `php artisan crm:create-admin`. Lệnh này cũng dùng để **lấy lại quyền** khi quên mật khẩu tài khoản quản trị nền tảng.

## 2. Quản lý nhân viên (menu *Người dùng*)

| Việc | Cách làm |
|---|---|
| Tạo tài khoản | **Tạo tài khoản**, nhập họ tên, email, phòng ban, vai trò, mật khẩu ban đầu |
| Khóa / mở khóa | Bấm 🔒 ở dòng nhân viên. Người bị khóa bị đăng xuất ngay lập tức |
| Đổi vai trò, phòng ban | Bấm ✏️ để sửa |
| Đặt lại mật khẩu | Mở chi tiết nhân viên: **Gửi liên kết đặt lại qua email**, hoặc **Đặt mật khẩu tạm thời** |
| Xóa | Bấm 🗑 (xóa mềm, dữ liệu nhân viên đó đã tạo vẫn được giữ) |

> Máy chủ chưa cấu hình email thật (`MAIL_MAILER=log`), nên liên kết đặt lại mật khẩu được ghi vào `storage/logs/laravel.log`. Cách đơn giản nhất là dùng **Đặt mật khẩu tạm thời** rồi báo nhân viên đổi lại sau khi đăng nhập.

Quản trị viên tổ chức không thể sửa, khóa, xóa hay tắt 2FA của Chủ sở hữu và quản trị nền tảng, và không thể gán vai trò Chủ sở hữu cho người khác. Mật khẩu mới phải có tối thiểu 10 ký tự, gồm chữ hoa, chữ thường và chữ số.

## 3. Vai trò và phân quyền

Có hai tầng:
- **Quản trị nền tảng** (*Platform Super Administrator*): người tạo hệ thống. Toàn quyền ở mọi tổ chức, quản lý danh sách tổ chức (menu *Quản lý tổ chức*).
- **Vai trò trong tổ chức:** mỗi tổ chức có bộ 14 vai trò riêng; chỉnh quyền ở tổ chức này không ảnh hưởng tổ chức khác.

| Vai trò | Phạm vi mặc định |
|---|---|
| Organization Owner (Chủ sở hữu) | Toàn quyền trong tổ chức; chỉ chủ sở hữu gán được vai trò chủ sở hữu |
| Organization Administrator | Toàn bộ chức năng, trừ thao tác trên chủ sở hữu và quản trị nền tảng |
| Sales Manager / Sales Employee | Quản lý: mọi dữ liệu bán hàng của nhóm. Nhân viên: chỉ dữ liệu **mình phụ trách hoặc tự tạo** |
| Marketing Manager / Marketing Employee | Chiến dịch, lead, xem khách hàng |
| Customer Support Manager / Support Agent | Ticket hỗ trợ, khách hàng, người liên hệ |
| Finance Manager / Accountant | Hóa đơn, thanh toán, báo cáo |
| Inventory Manager, Project Manager, Employee | Theo ma trận mặc định, chủ sở hữu tự điều chỉnh |
| Read-only Auditor | Chỉ xem (kể cả nhật ký hoạt động), không tạo, sửa, xóa |

**Chỉnh quyền:** vào *Vai trò & quyền*, bấm **Chỉnh quyền**, tích hoặc bỏ tích ma trận *Xem / Xem tất cả / Tạo / Sửa / Xóa / Xuất / Phân công / Duyệt / Quản trị / Sử dụng* cho từng module.
- **Xem tất cả:** thấy dữ liệu của mọi nhân viên. Không tích thì chỉ thấy dữ liệu mình phụ trách.
- **Kết nối API → Sử dụng:** được tạo khóa API (mặc định chỉ Chủ sở hữu và Quản trị viên).
- Quyền được kiểm tra ở **máy chủ**, không chỉ ẩn nút. Người không có quyền mà cố truy cập sẽ nhận lỗi 403.
- Có thể **tạo vai trò mới** tùy ý. Vai trò mặc định không xóa được. Chi tiết kỹ thuật: [RBAC.md](RBAC.md).

## 3a. Tổ chức (không gian làm việc)

- Một tài khoản có thể thuộc nhiều tổ chức; chuyển tổ chức ở ô **Không gian làm việc** góc dưới menu trái. Dữ liệu mỗi tổ chức hoàn toàn tách biệt.
- Quản trị nền tảng tạo tổ chức mới kèm chủ sở hữu ở menu *Quản lý tổ chức*; có thể tạm ngưng một tổ chức (thành viên không làm việc được cho tới khi mở lại).
- Thêm nhân viên đã có tài khoản ở tổ chức khác: nhập đúng email của họ khi **Tạo tài khoản** — họ dùng mật khẩu sẵn có. Với tài khoản dùng chung, quản trị tổ chức chỉ đổi được vai trò, phòng ban và trạng thái thành viên.
- *Hạn chế hiện tại:* Cài đặt hệ thống (tên công ty, logo...) và phòng ban của nhân viên còn dùng chung giữa các tổ chức — sẽ tách ở Phase 2.

## 3b. Bảo mật

- Mỗi người tự bật **xác thực 2 lớp** ở *ảnh đại diện → Bảo mật tài khoản* (quét QR bằng Google/Microsoft Authenticator, lưu 8 mã khôi phục). Ở đây cũng có **lịch sử đăng nhập** và **xác minh email**.
- Nhân viên mất điện thoại và mã khôi phục: mở chi tiết nhân viên → **Tắt xác thực 2 lớp** (hãy xác minh đúng người trước).
- Quản trị nền tảng có thể bật **Bắt buộc xác thực 2 lớp cho tài khoản quản trị** trong *Cài đặt hệ thống*.
- **Mã xác thực qua email:** mỗi người tự bật ở *Bảo mật tài khoản*; quản trị nền tảng có thể **bắt buộc cho mọi người** (*Cài đặt → Bảo mật đăng nhập*) — chỉ bật được khi *Email gửi đi* đã hoạt động. Người dùng ứng dụng xác thực vẫn dùng ứng dụng. Nhân viên không vào được email: kiểm tra địa chỉ email trong hồ sơ nhân viên.
- **Link mời đăng ký** (*Người dùng → Link mời đăng ký*): người đăng ký phải xác thực email bằng mã 6 số; tài khoản vào đúng tổ chức + vai trò của link. Không mời được Chủ sở hữu; chỉ chủ sở hữu / quản trị nền tảng mời được Quản trị viên. Gửi link qua kênh riêng (Zalo cá nhân…), không đăng công khai; thu hồi link khi đã đủ người.
- Đăng nhập từ thiết bị lạ sẽ có cảnh báo trong chuông thông báo và email.
- Khóa API cho phần mềm khác: *ảnh đại diện → Khóa API*; quản trị viên thấy và thu hồi được khóa của mọi thành viên. Xem [API.md](API.md).
- Tổng hợp và danh sách việc trước khi đưa lên Internet: [SECURITY.md](SECURITY.md).

## 4. Phòng ban

Menu *Phòng ban*: thêm, sửa, xóa phòng ban (chỉ xóa được khi phòng ban không còn nhân viên).

## 5. Cài đặt hệ thống

Menu *Cài đặt hệ thống*: tên công ty, logo, màu thương hiệu, email, số điện thoại, địa chỉ (hiện trên PDF báo giá và hóa đơn), đơn vị tiền tệ, định dạng ngày, thuế VAT mặc định, bật/tắt từng loại thông báo.

Mặc định: Tiếng Việt · Asia/Ho_Chi_Minh · VND · DD/MM/YYYY.

### Trang đăng nhập & thương hiệu

*Cài đặt hệ thống → tab Trang đăng nhập & thương hiệu:*
- Tên thương hiệu, dòng phụ, khẩu hiệu; tiêu đề và đoạn giới thiệu trên trang đăng nhập.
- **Dải chỉ số** (tối đa 4 dòng, mỗi dòng `giá trị|nhãn`). Đây là chữ hiển thị, hãy dùng số liệu đúng với doanh nghiệp. Để trống nếu muốn ẩn.
- Dòng bản quyền, chủ sở hữu bản quyền.
- **Ảnh nền** trang đăng nhập (tối đa 4MB).
- Nội dung 3 trang **Điều khoản sử dụng / Chính sách bảo mật / Hỗ trợ**. Để trống thì dùng nội dung mẫu.

### Đăng nhập bằng Google / Facebook

1. Tạo ứng dụng OAuth tại Google Cloud Console (*APIs & Services → Credentials*) hoặc Facebook Developers.
2. Khai báo **URL chuyển hướng** đúng như hiển thị trong trang cài đặt, ví dụ `http://localhost/orangecrm/auth/google/callback`.
3. Dán Client ID và Client Secret vào trang cài đặt, rồi bấm **Lưu**. Khóa bí mật được mã hóa trước khi lưu.
4. Chỉ nhân viên **đã được cấp tài khoản với đúng email đó** mới đăng nhập được. Hệ thống không tự tạo tài khoản mới.

Khi chưa cấu hình, bấm nút Google/Facebook sẽ hiện thông báo "chưa được bật".

### Tên đăng nhập

Mỗi nhân viên có thể có thêm **tên đăng nhập** (không bắt buộc, gồm chữ không dấu, số, `.`, `_`, `-`). Nhân viên đăng nhập được bằng **email hoặc tên đăng nhập**. Đặt trong *Người dùng → Sửa*.

## 6. Nhật ký hoạt động

Menu *Nhật ký hoạt động* ghi lại: ai làm gì, lúc nào, trên dữ liệu nào, giá trị cũ và mới, kèm địa chỉ IP. Ghi cả đăng nhập, đăng nhập sai, xuất dữ liệu, phân công, duyệt đơn, thanh toán. **Không bao giờ ghi mật khẩu hay token.**

## 7. Trước khi dùng thật: xóa dữ liệu demo

```
php artisan crm:purge-demo
```

Chỉ xóa bản ghi demo (`is_demo = 1`) và các tài khoản nhân viên demo. Sau đó dải vàng "DỮ LIỆU DEMO" ở đầu trang sẽ biến mất (trong tối đa 10 phút).

## 8. Sao lưu định kỳ

Xem [DATABASE_SETUP.md](DATABASE_SETUP.md) mục 3 và 4. Khuyến nghị sao lưu ít nhất **mỗi tuần một lần**, lưu bản sao ở nơi khác máy tính (USB, Google Drive).

## 9. Các lệnh quản trị hữu ích

Chạy trong Command Prompt tại `C:\phuc-crm`:

| Lệnh | Tác dụng |
|---|---|
| `php artisan crm:health` | Kiểm tra tình trạng hệ thống |
| `php artisan crm:install` | Cập nhật cấu trúc database (an toàn) |
| `php artisan crm:create-admin` | Tạo hoặc đặt lại tài khoản quản trị nền tảng |
| `php artisan crm:purge-demo` | Xóa dữ liệu demo |
| `php artisan crm:task-reminders` | Chạy nhắc việc ngay (bình thường tự chạy mỗi 15 phút khi CRM đang mở) |
| `php artisan crm:seed-demo 1` | Nạp dữ liệu DEMO vào tổ chức số 1 để thử nghiệm (không chạy ở production; xóa lại bằng `crm:purge-demo`) |
| *(giao diện)* Hệ thống → Email gửi đi | Nhập SMTP (Gmail cần mật khẩu ứng dụng), gửi email thử — ghi đè MAIL_* trong .env khi bật |
| `php artisan crm:contract-reminders` | Nhắc hợp đồng sắp / đã hết hạn (tự chạy 08:05 mỗi ngày) |
| `php artisan crm:asset-reminders` | Nhắc giấy phép phần mềm sắp / đã hết hạn và tài sản mượn quá hạn (tự chạy 08:00 mỗi ngày) |
| `php artisan crm:prune-attendance-selfies` | Xóa ảnh selfie chấm công quá hạn lưu (tự chạy 03:00 mỗi ngày; giờ công giữ nguyên) |
| `php artisan optimize:clear` | Xóa bộ nhớ đệm khi giao diện hiển thị sai |
