# Xử lý sự cố

**Bước đầu tiên luôn là:** bấm đúp `CHECK_CRM.bat` và đọc các dòng `[LỖI]`.

## Không mở được CRM

| Hiện tượng | Nguyên nhân | Cách xử lý |
|---|---|---|
| Trình duyệt báo *"This site can't be reached"* ở `127.0.0.1:8000` | Chưa chạy CRM | Bấm đúp `START_CRM.bat` |
| `http://localhost/orangecrm` báo **404 Not Found** | Apache chưa nạp cấu hình OrangeCRM | XAMPP Control Panel: **Stop** rồi **Start** dòng Apache |
| `http://localhost/orangecrm` báo **403 Forbidden** | Truy cập từ máy khác (chỉ cho phép máy này) | Dùng trên chính máy cài CRM |
| `START_CRM.bat` báo *"Cổng 8000 đang bị chương trình khác sử dụng"* | Phần mềm khác chiếm cổng 8000 | Tắt phần mềm đó, hoặc sửa `set "PORT=8000"` trong `START_CRM.bat` thành `8080` |
| Báo *"MySQL không khởi động được"* | MySQL lỗi hoặc cổng 3306 bị chiếm | Mở XAMPP Control Panel, bấm **Start** ở MySQL, bấm **Logs** để xem lỗi |
| MySQL tự tắt ngay sau khi bật | Tắt máy đột ngột làm hỏng tệp tạm | Xem mục "MySQL tự tắt" bên dưới |

### MySQL bật lên vài giây rồi tự tắt (lỗi "Failed to initialize multi master structures")

Đã xảy ra ngày 14/10/2026. Nguyên nhân: MySQL được khởi động ngầm mà không chỉ định tệp nhật ký, nên ghi nhật ký nhầm vào tệp `C:\xampp\mysql\data\multi-master.info`. Từ đó MySQL tưởng có máy chủ sao chép và tự hủy.

Cách sửa:
1. Tắt hết MySQL.
2. Chuyển các tệp `multi-master.info`, `master-*`, `relay-log-*`, `mysql-relay-bin-*` trong `C:\xampp\mysql\data` sang một thư mục sao lưu (bản ngày 14/10 nằm ở `C:\xampp\mysql\data_junk_backup_20261014`).
3. Bật lại MySQL.

`START_CRM.bat` và trình giám sát nay luôn khởi động MySQL kèm `--log-error`, nên lỗi này không lặp lại.

### MySQL tự tắt
1. Mở `C:\xampp\mysql\data\mysql_error.log`, xem các dòng cuối.
2. Nếu có chữ `Aria` hoặc `crash recovery`: sao lưu thư mục `C:\xampp\mysql\data` trước, rồi trong XAMPP Control Panel bấm Start lại MySQL vài lần.
3. Vẫn lỗi: liên hệ người hỗ trợ kỹ thuật, kèm tệp log trên. **Không xóa thư mục data.**

## Lỗi khi đang dùng

| Mã lỗi / thông báo | Ý nghĩa | Cách xử lý |
|---|---|---|
| **403** *Bạn không có quyền...* | Vai trò của bạn không có quyền này | Nhờ quản trị viên cấp quyền (*Vai trò & quyền*) |
| **404** *Không tìm thấy* | Dữ liệu đã bị xóa hoặc đường dẫn sai | Quay lại danh sách |
| **419** *Phiên làm việc đã hết hạn* | Để trang mở quá lâu (quá 120 phút) | Tải lại trang (F5) rồi thao tác lại |
| **422** / ô nhập viền đỏ | Dữ liệu nhập chưa hợp lệ | Đọc dòng chữ đỏ dưới ô và sửa |
| **429** *Thao tác quá nhanh* | Gửi quá nhiều yêu cầu, hoặc đăng nhập sai nhiều lần | Đợi 1–5 phút |
| **500** *Server Error* | Lỗi hệ thống | Xem mục bên dưới |
| *Tài khoản đã bị khóa* | Quản trị viên đã khóa tài khoản | Liên hệ quản trị viên |

### Lỗi 500
1. Mở tệp `C:\phuc-crm\storage\logs\laravel.log`, cuộn xuống cuối để xem lỗi mới nhất.
2. Thử chạy trong Command Prompt tại `C:\phuc-crm`: `php artisan optimize:clear`
3. Chạy `php artisan crm:install` (bổ sung bảng còn thiếu, không xóa dữ liệu).
4. Vẫn lỗi: gửi đoạn cuối tệp log cho người hỗ trợ kỹ thuật.

## Giao diện hiển thị lỗi (mất màu, mất biểu tượng)

```
npm run build
php artisan optimize:clear
```
Sau đó nhấn **Ctrl + F5** trên trình duyệt.

## Biểu đồ không hiện

Biểu đồ được tải bằng AJAX. Nếu thấy dòng báo lỗi đỏ trong khung biểu đồ, hãy tải lại trang. Nếu khung trống *"Không có dữ liệu trong kỳ"* nghĩa là khoảng thời gian đã chọn chưa có dữ liệu, hãy chọn *Năm nay*.

## Quên mật khẩu Super Admin

Mở Command Prompt tại `C:\phuc-crm`:
```
php artisan crm:create-admin
```
Nhập đúng email Super Admin cũ và mật khẩu mới.

## Xuất Excel bị lỗi font tiếng Việt (CSV)

Tệp CSV đã có BOM UTF-8. Nếu Excel vẫn hiển thị sai, hãy chọn xuất **Excel (.xlsx)** thay vì CSV.

## Vị trí các tệp nhật ký (log)

| Log | Đường dẫn |
|---|---|
| Laravel (ứng dụng) | `C:\phuc-crm\storage\logs\laravel.log` |
| PHP | `C:\xampp\php\logs\php_error_log` |
| Apache | `C:\xampp\apache\logs\error.log` |
| MySQL | `C:\xampp\mysql\data\mysql_error.log` |
