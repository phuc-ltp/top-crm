# Hướng dẫn triển khai

> Trước khi đưa lên Internet, làm đủ danh sách bắt buộc trong [SECURITY.md](SECURITY.md#5-bắt-buộc-trước-khi-đưa-lên-internet) (tắt APP_DEBUG, HTTPS, mật khẩu database, SMTP, bắt buộc 2FA cho quản trị...).

## 1. Hiện trạng (máy tính cá nhân)

- Ứng dụng chạy **chỉ trên máy này**: Laravel tại `127.0.0.1:8000`, Apache tại `localhost/orangecrm` với `Require local`.
- `APP_ENV=local`, `APP_DEBUG=true`: chỉ phù hợp để dùng và phát triển trên máy cá nhân.
- Hệ thống **chưa** được mở ra Internet. Đừng mở cổng router hay tắt `Require local` khi chưa làm đủ các bước ở mục 2.

## 2. Nếu muốn cho nhiều người dùng qua mạng hoặc Internet

Hãy nhờ người có chuyên môn thực hiện các bước sau:

1. **Máy chủ riêng** (VPS Linux hoặc Windows Server) có PHP 8.2+, MySQL 8 / MariaDB 10.6+, Nginx hoặc Apache, HTTPS (Let's Encrypt).
2. Tệp `.env` cho môi trường thật:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://crm.tencongty.vn
   CRM_DEMO_DATA=false
   SESSION_SECURE_COOKIE=true
   DB_PASSWORD=<mật khẩu mạnh, KHÔNG dùng root>
   ```
3. Tạo user MySQL riêng chỉ có quyền trên `orangecrm_db`, đặt mật khẩu cho root.
4. Trỏ DocumentRoot vào thư mục `public/` (không bao giờ trỏ vào thư mục gốc).
5. Chạy lần lượt:
   ```
   composer install --no-dev --optimize-autoloader
   php artisan key:generate        (chỉ lần đầu)
   php artisan crm:install
   npm ci && npm run build
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
6. Cron cho bộ nhắc việc: `* * * * * php /đường-dẫn/artisan schedule:run`
7. Cấu hình email thật (SMTP) trong `.env` để gửi liên kết đặt lại mật khẩu.
8. Sao lưu database tự động hằng ngày, lưu bản sao ở nơi khác.
9. Xóa dữ liệu demo: `php artisan crm:purge-demo`.
10. Bổ sung `laravel/sanctum` nếu cần API cho ứng dụng bên ngoài.

## 3. Chuyển sang máy tính khác

1. Sao lưu database (xem [DATABASE_SETUP.md](DATABASE_SETUP.md)) và thư mục `storage\app`.
2. Trên máy mới: làm theo [INSTALLATION.md](INSTALLATION.md).
3. Khôi phục database và chép lại `storage\app`.
