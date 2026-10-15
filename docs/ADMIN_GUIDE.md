# Hướng dẫn quản trị OrangeCRM

## 1. Tài khoản quản trị đầu tiên

- Khi hệ thống chưa được khởi tạo, mọi truy cập đều được chuyển tới trang **/setup** để bạn tự tạo tài khoản (tự đặt mật khẩu). Sau khi tạo xong, trang này **khóa vĩnh viễn**, kể cả khi tài khoản Super Admin về sau bị xóa (chống người lạ chiếm quyền). Dù có nhiều yêu cầu gửi cùng lúc thì cũng chỉ một tài khoản được tạo.
- Cách khác: mở Command Prompt tại `C:\phuc-crm` và chạy `php artisan crm:create-admin`. Lệnh này cũng dùng để **lấy lại quyền** khi quên mật khẩu Super Admin.

## 2. Quản lý nhân viên (menu *Người dùng*)

| Việc | Cách làm |
|---|---|
| Tạo tài khoản | **Tạo tài khoản**, nhập họ tên, email, phòng ban, vai trò, mật khẩu ban đầu |
| Khóa / mở khóa | Bấm 🔒 ở dòng nhân viên. Người bị khóa bị đăng xuất ngay lập tức |
| Đổi vai trò, phòng ban | Bấm ✏️ để sửa |
| Đặt lại mật khẩu | Mở chi tiết nhân viên: **Gửi liên kết đặt lại qua email**, hoặc **Đặt mật khẩu tạm thời** |
| Xóa | Bấm 🗑 (xóa mềm, dữ liệu nhân viên đó đã tạo vẫn được giữ) |

> Máy chủ chưa cấu hình email thật (`MAIL_MAILER=log`), nên liên kết đặt lại mật khẩu được ghi vào `storage/logs/laravel.log`. Cách đơn giản nhất là dùng **Đặt mật khẩu tạm thời** rồi báo nhân viên đổi lại sau khi đăng nhập.

Administrator không thể sửa, khóa hay xóa Super Admin, và không thể gán vai trò Super Admin cho người khác.

## 3. Vai trò và phân quyền

| Vai trò | Phạm vi mặc định |
|---|---|
| Super Admin | Toàn quyền, không thể giới hạn |
| Administrator | Toàn bộ chức năng, trừ thao tác trên Super Admin |
| Sales Manager | Mọi dữ liệu bán hàng của cả nhóm, duyệt đơn, phân công, báo cáo |
| Sales Staff | Chỉ khách hàng, lead, cơ hội, báo giá, đơn hàng **mình phụ trách hoặc tự tạo** |
| Marketing Staff | Chiến dịch, toàn bộ lead, xem khách hàng |
| Customer Support | Ticket hỗ trợ, xem và cập nhật khách hàng, người liên hệ |
| Viewer | Chỉ xem, không tạo, sửa, xóa |

**Chỉnh quyền:** vào *Vai trò & quyền*, bấm **Chỉnh quyền**, tích hoặc bỏ tích ma trận *Xem / Xem tất cả / Tạo / Sửa / Xóa / Xuất / Phân công / Duyệt / Quản trị* cho từng module.
- **Xem tất cả:** thấy dữ liệu của mọi nhân viên. Không tích thì chỉ thấy dữ liệu mình phụ trách.
- Quyền được kiểm tra ở **máy chủ**, không chỉ ẩn nút. Người không có quyền mà cố truy cập sẽ nhận lỗi 403.
- Có thể **tạo vai trò mới** tùy ý. Vai trò mặc định không xóa được.

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

Chỉ xóa bản ghi demo (`is_demo = 1`) và 7 tài khoản nhân viên demo. Sau đó dải vàng "DỮ LIỆU DEMO" ở đầu trang sẽ biến mất (trong tối đa 10 phút).

## 8. Sao lưu định kỳ

Xem [DATABASE_SETUP.md](DATABASE_SETUP.md) mục 3 và 4. Khuyến nghị sao lưu ít nhất **mỗi tuần một lần**, lưu bản sao ở nơi khác máy tính (USB, Google Drive).

## 9. Các lệnh quản trị hữu ích

Chạy trong Command Prompt tại `C:\phuc-crm`:

| Lệnh | Tác dụng |
|---|---|
| `php artisan crm:health` | Kiểm tra tình trạng hệ thống |
| `php artisan crm:install` | Cập nhật cấu trúc database (an toàn) |
| `php artisan crm:create-admin` | Tạo hoặc đặt lại Super Admin |
| `php artisan crm:purge-demo` | Xóa dữ liệu demo |
| `php artisan crm:task-reminders` | Chạy nhắc việc ngay (bình thường tự chạy mỗi 15 phút khi CRM đang mở) |
| `php artisan optimize:clear` | Xóa bộ nhớ đệm khi giao diện hiển thị sai |
