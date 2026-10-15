# Hướng dẫn cài đặt OrangeCRM

Tài liệu này dành cho trường hợp **cài lên một máy tính mới**. Trên máy hiện tại, hệ thống đã được cài sẵn, bạn chỉ cần chạy `START_CRM.bat`.

## 1. Phần mềm cần có

| Phần mềm | Phiên bản | Tải ở đâu |
|---|---|---|
| XAMPP (có PHP 8.2 và MySQL/MariaDB) | 8.2.x | https://www.apachefriends.org |
| Composer | 2.x | https://getcomposer.org |
| Node.js (kèm npm) | 18 trở lên | https://nodejs.org |

Khi cài XAMPP, hãy để thư mục mặc định `C:\xampp`. Nếu cài chỗ khác, tạo biến môi trường `XAMPP_HOME` trỏ tới thư mục đó.

### Bật các tiện ích PHP cần thiết

Mở tệp `C:\xampp\php\php.ini` bằng Notepad, tìm các dòng sau và **xóa dấu `;` ở đầu dòng**:

```
extension=gd
extension=intl
extension=zip
```

Lưu tệp lại, sau đó Stop rồi Start Apache trong XAMPP Control Panel.

## 2. Chép mã nguồn

Chép toàn bộ thư mục `phuc-crm` vào ổ C, thành `C:\phuc-crm`.

> **Không** đặt thư mục này vào `C:\xampp\htdocs`. Để ngoài htdocs thì người khác không đọc được tệp cấu hình bí mật `.env`.

## 3. Cài đặt tự động

1. Mở XAMPP Control Panel, bấm **Start** ở dòng **MySQL**.
2. Trong thư mục `C:\phuc-crm`, sao chép tệp `.env.example` thành `.env` (nếu chưa có).
3. Mở **Command Prompt** tại thư mục `C:\phuc-crm` (gõ `cmd` vào thanh địa chỉ của File Explorer rồi Enter) và chạy lần lượt:

```
composer install
php artisan key:generate
php artisan crm:install
npm install
npm run build
```

4. Mở `.env` bằng Notepad và kiểm tra các dòng sau:

```
APP_URL=http://localhost/orangecrm
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi
DB_DATABASE=orangecrm_db
DB_USERNAME=root
DB_PASSWORD=          (để trống nếu MySQL của XAMPP chưa đặt mật khẩu)
CRM_DEMO_DATA=false   (đổi thành true nếu muốn có dữ liệu mẫu)
```

5. (Tùy chọn) Muốn có dữ liệu mẫu để thử: đặt `CRM_DEMO_DATA=true` rồi chạy `php artisan db:seed`.
6. Bấm đúp `START_CRM.bat`. Trình duyệt sẽ mở trang tạo tài khoản quản trị.

## 4. Cấu hình Apache (để dùng địa chỉ http://localhost/orangecrm)

1. Tạo tệp `C:\xampp\apache\conf\extra\httpd-orangecrm.conf` với nội dung:

```apache
Alias /orangecrm "C:/phuc-crm/public"
<Directory "C:/phuc-crm/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require local
</Directory>
```

2. Mở `C:\xampp\apache\conf\httpd.conf` và thêm dòng sau vào cuối tệp:

```
Include conf/extra/httpd-orangecrm.conf
```

3. Trong XAMPP Control Panel, bấm **Stop** rồi **Start** ở dòng Apache.

`Require local` nghĩa là chỉ chính máy này truy cập được, máy khác trong mạng sẽ bị chặn.

## 5. Kiểm tra

Bấm đúp `CHECK_CRM.bat`. Tất cả các dòng phải hiện `[OK]`.
