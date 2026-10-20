#!/usr/bin/env bash
# ==============================================================================
#  Triển khai / cập nhật TOP CRM trên máy chủ Linux (Ubuntu). Chạy trong thư mục dự án:
#      bash scripts/deploy.sh
#  Yêu cầu: PHP 8.2+ (pdo_mysql, mbstring, openssl, fileinfo, gd, zip, intl, bcmath), Composer, Node 18+, MySQL/MariaDB.
#  An toàn: dừng ngay khi có lỗi; bật chế độ bảo trì trong lúc cập nhật; sao lưu database trước khi migrate.
# ==============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Thiếu tệp .env — sao chép từ .env.production.example và điền giá trị thật."; exit 1; }
grep -q '^APP_ENV=production' .env || { echo "APP_ENV trong .env chưa phải production — dừng."; exit 1; }

echo "==> Bật chế độ bảo trì"
php artisan down --retry=15 || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Sao lưu database trước khi cập nhật"
bash scripts/backup.sh

echo "==> Cập nhật mã nguồn"
git pull --ff-only

echo "==> Thư viện PHP (không gồm gói dev)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Build giao diện"
npm ci --no-audit --no-fund
npm run build

echo "==> Cập nhật cấu trúc database"
php artisan migrate --force

echo "==> Tối ưu cấu hình"
php artisan storage:link 2>/dev/null || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Kiểm tra trước khi mở lại"
php artisan crm:production-check

php artisan up
trap - EXIT
echo "==> Hoàn tất."
