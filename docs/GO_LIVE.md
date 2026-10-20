# Đưa TOP CRM lên web công khai (go-live)

Tài liệu cho người triển khai: bạn, hoặc người hỗ trợ kỹ thuật được bạn tin tưởng. Làm **theo đúng thứ tự**; xong mỗi bước thì đánh dấu.
Mục tiêu: hệ thống chạy trên VPS Linux, có HTTPS, dữ liệu được sao lưu tự động, và **không lộ thông tin bí mật**.

## 0. Chuẩn bị
- [ ] **VPS Ubuntu 22.04 / 24.04**, tối thiểu 2 GB RAM, 20 GB ổ đĩa. Ví dụ nhà cung cấp tại Việt Nam: Viettel IDC, VNPT, BizFly, Vultr Singapore.
- [ ] **Tên miền** (ví dụ `crm.congty.vn`), trỏ bản ghi **A** về IP của VPS.
- [ ] **Tài khoản gửi thư SMTP** (Gmail với mật khẩu ứng dụng, Zoho, SendGrid…), dùng cho quên mật khẩu và thông báo.
- [ ] Trên máy hiện tại: chạy `SAO_LUU.bat` để có bản sao lưu mới nhất trong `C:\TOPCRM_Backup`.

## 1. Cài phần mềm trên VPS
```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mariadb-server git unzip curl ufw \
  php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt -y install nodejs
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable   # chỉ mở SSH, 80, 443
sudo mysql_secure_installation                                            # đặt mật khẩu root, xóa user ẩn danh
```
> Ubuntu bản cũ không có sẵn `php8.2-*` thì thêm kho: `sudo add-apt-repository ppa:ondrej/php`.

## 2. Tạo database và user riêng (không dùng root)
```bash
sudo mysql -e "CREATE DATABASE orangecrm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'topcrm'@'localhost' IDENTIFIED BY 'MAT_KHAU_MANH_IT_NHAT_20_KY_TU';
GRANT ALL PRIVILEGES ON orangecrm_db.* TO 'topcrm'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. Lấy mã nguồn và cấu hình
```bash
sudo mkdir -p /var/www && sudo chown $USER:www-data /var/www && cd /var/www
git clone https://github.com/phuc-ltp/top-crm.git topcrm && cd topcrm
cp .env.production.example .env && chmod 640 .env && nano .env
```
Điền vào `.env`:
- **`APP_KEY`**: chép **nguyên** từ `C:\phuc-crm\.env` trên máy hiện tại. Đây là khóa giải mã thông tin nhạy cảm (CCCD, ngày sinh, khóa 2FA). Sai khóa thì các dữ liệu đó không đọc được.
- `APP_URL`: `https://` + tên miền.
- `DB_PASSWORD`: mật khẩu user `topcrm` ở bước 2.
- `MAIL_*`: thông tin SMTP.

## 4. Chuyển dữ liệu từ máy hiện tại
Trên máy Windows, lấy tệp mới nhất trong `C:\TOPCRM_Backup\database\` (`orangecrm_db_YYYYMMDD_HHMM.sql.zip`), giải nén ra `.sql` rồi chép lên VPS (dùng WinSCP):
```bash
mysql -u topcrm -p orangecrm_db < orangecrm_db_YYYYMMDD_HHMM.sql
```
Chép thêm tệp tải lên:
- `C:\TOPCRM_Backup\uploads\public` → `/var/www/topcrm/storage/app/public` (ảnh đại diện, logo);
- `C:\TOPCRM_Backup\uploads\private` → `/var/www/topcrm/storage/app/private` (tệp đính kèm, chỉ tải qua kiểm tra quyền).

## 5. Cài đặt và build
```bash
cd /var/www/topcrm
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
```

## 6. Nginx và HTTPS
```bash
sudo cp scripts/nginx-topcrm.conf /etc/nginx/sites-available/topcrm
sudo nano /etc/nginx/sites-available/topcrm          # sửa server_name, root
sudo ln -s /etc/nginx/sites-available/topcrm /etc/nginx/sites-enabled/ && sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo apt -y install certbot python3-certbot-nginx && sudo certbot --nginx -d crm.congty.vn   # HTTPS miễn phí, tự gia hạn
```

## 7. Tác vụ định kỳ và sao lưu tự động
`crontab -e`, thêm 2 dòng:
```
* * * * * cd /var/www/topcrm && php artisan schedule:run >> /dev/null 2>&1
15 2 * * * cd /var/www/topcrm && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
```
- Dòng 1: nhắc việc, hóa đơn quá hạn, dọn thông báo… (thay cho `schedule:work` trên Windows).
- Dòng 2: sao lưu lúc 2:15 mỗi đêm, giữ 30 ngày. **Hãy chép thư mục `~/topcrm-backups` ra nơi khác định kỳ.**

## 8. Kiểm tra trước khi công bố
```bash
php artisan crm:production-check
```
- Phải hết mọi dòng **[LỖI]**; nên xử lý cả **[CẢNH BÁO]**.
- Đăng nhập bằng tài khoản của bạn. Vào **Cài đặt › Bảo mật**, bật **bắt buộc xác thực 2 lớp cho quản trị**, rồi tự bật 2FA cho tài khoản mình.
- Kiểm tra quên mật khẩu (nhận được email), tải ảnh đại diện, xuất Excel.

## 9. Cập nhật phiên bản về sau
```bash
cd /var/www/topcrm && bash scripts/deploy.sh
```
Script tự bật chế độ bảo trì, sao lưu, kéo mã mới, cài thư viện, build, migrate, cache, rồi chạy `crm:production-check` trước khi mở lại.

## Những điều KHÔNG được làm
- Không bật `APP_DEBUG=true` trên máy chủ thật. Mỗi khi có lỗi, người lạ sẽ thấy mã nguồn và cấu hình.
- Không đưa `.env`, tệp sao lưu `.sql` hay thư mục `storage/` lên GitHub hoặc thư mục `public/`.
- Không đổi `APP_KEY` sau khi đã có dữ liệu.
- Không dùng tài khoản MySQL `root` cho ứng dụng; không mở cổng 3306 ra Internet.

## Đã có sẵn trong ứng dụng
- **Security headers:** chống nhúng iframe, chống đoán MIME, Referrer-Policy, Permissions-Policy. Ở production có thêm **Content-Security-Policy** và **HSTS** (khi chạy HTTPS).
- **Mã hóa và URL:** mọi URL sinh ra đều dùng `https` khi `APP_URL` là https. Phiên đăng nhập được mã hóa, cookie chỉ gửi qua HTTPS.
- **Chống tấn công:** chống dò mật khẩu (giới hạn số lần đăng nhập), CSRF cho mọi form.
- **Trang và dữ liệu:** trang lỗi thân thiện không lộ chi tiết; dữ liệu cách ly theo tổ chức; thông tin nhạy cảm nhân viên được mã hóa.
