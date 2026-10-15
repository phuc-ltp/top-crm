# Nâng cấp MariaDB 10.4.32 → 10.11.19 LTS (15/10/2026)

## Kết quả
- Phiên bản mới: **MariaDB 10.11.19**. Dòng LTS này được hỗ trợ bảo mật tới 02/2028. Bản 10.4 cũ đã hết hỗ trợ từ 06/2024 và từng gặp sự cố tự tắt.
- Bản cài đặt được tải từ trang chính thức downloads.mariadb.org và đã kiểm tra mã SHA-512 khớp.
- Dữ liệu: 5 CSDL, 157 bảng, 1.832 dòng. Số dòng **khớp 100% từng bảng** trước và sau. `orangecrm_db` vẫn đủ 46 bảng, 69 khóa ngoại, 181 chỉ mục; `mysqlcheck` không báo lỗi.
- 80/80 bài kiểm thử tự động đạt, thời gian chạy giảm từ khoảng 140 giây xuống 88 giây. phpMyAdmin 5.2.1 hoạt động bình thường.

## Cách làm
1. Sao lưu ba lớp: chạy `SAO_LUU.bat`; xuất từng CSDL ra `C:\TOPCRM_Backup\mariadb_upgrade_20261015\*.sql` kèm số dòng từng bảng (`rowcounts_before.txt`); giữ nguyên thư mục MySQL cũ.
2. Tắt MariaDB 10.4 sạch sẽ, đổi tên `C:\xampp\mysql` thành `C:\xampp\mysql_10.4.32_old` (không xóa).
3. Chép MariaDB 10.11 vào `C:\xampp\mysql`, khởi tạo **thư mục dữ liệu mới sạch** bằng `mysql_install_db`. Cách này cũng loại bỏ các tệp hỏng từng gây sự cố trước đây.
4. Tạo lại tài khoản giống hệt bản cũ (`root@localhost/127.0.0.1/::1` không mật khẩu, `pma@localhost`), rồi nhập lại dữ liệu và so sánh số dòng (`rowcounts_after.txt`).
5. Lần chạy đầu bị tự động hoàn tác vì bước kiểm tra kết nối dùng nhầm cổng 3307 (phần `[client]` trong `my.ini` cũ của XAMPP). Đã sửa rồi chạy lại thành công.

## Thay đổi cấu hình (`C:\xampp\mysql\bin\my.ini`)
| Thông số | Cũ | Mới | Lý do |
|---|---|---|---|
| `max_allowed_packet` | 1M | 64M | Tránh lỗi khi nhập hoặc xuất dữ liệu lớn |
| `innodb_log_file_size` | 5M | 64M | Ghi dữ liệu ổn định và nhanh hơn |
| `key_buffer` | 16M | `key_buffer_size=16M` | Ghi đúng tên đầy đủ |
| `bind-address` | (mọi địa chỉ) | `127.0.0.1` | **Bảo mật:** root không có mật khẩu nên không để máy khác trong mạng kết nối vào. CRM và phpMyAdmin đều dùng 127.0.0.1 nên không bị ảnh hưởng |

`sql_mode`, bảng mã, cổng 3306 và cách khởi động qua XAMPP Control Panel giữ nguyên. `resetroot.bat` của XAMPP không mang sang vì vốn không tương thích với MariaDB 10.4 trở lên.

## Hoàn tác (chỉ dùng khi thật cần)
1. Tắt MySQL: chạy `C:\xampp\mysql\bin\mysqladmin -uroot shutdown`, hoặc bấm Stop trong XAMPP Control Panel.
2. Đổi tên `C:\xampp\mysql` thành `C:\xampp\mysql_10.11`, rồi đổi `C:\xampp\mysql_10.4.32_old` thành `C:\xampp\mysql`.
3. Bấm Start MySQL trong XAMPP Control Panel.

Lưu ý: dữ liệu phát sinh sau ngày nâng cấp không có trong bản cũ, nên hãy xuất CSDL từ 10.11 trước khi hoàn tác. Tệp xuất bằng công cụ 10.11 có dòng "sandbox mode" mà bản 10.4 không đọc được; khi đó cần bỏ dòng đầu tiên của tệp.

Thư mục `C:\xampp\mysql_10.4.32_old` (khoảng 317 MB) nên giữ khoảng 2 tuần. Khi mọi thứ ổn định thì có thể xóa (hỏi Claude trước).
