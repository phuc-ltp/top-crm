# Tạo lại database/sql/orangecrm_db.sql từ migrations + dữ liệu cấu hình + DỮ LIỆU DEMO.
# Chạy trên một database TẠM (orangecrm_sqlgen) rồi xóa nó — KHÔNG đọc/ghi database thật (orangecrm_db).
# Cách chạy:  powershell -ExecutionPolicy Bypass -File scripts\generate-sql.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$bin = 'C:\xampp\mysql\bin'
$temp = 'orangecrm_sqlgen'
$out = Join-Path $root 'database\sql\orangecrm_db.sql'

if ($temp -eq 'orangecrm_db') { throw 'Từ chối: database tạm không được trùng database thật.' }

Push-Location $root
try {
    & "$bin\mysql.exe" -uroot -e "DROP DATABASE IF EXISTS $temp; CREATE DATABASE $temp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    $env:DB_DATABASE = $temp
    $env:CRM_DEMO_DATA = 'true'
    $actual = (php artisan tinker --execute="echo DB::connection()->getDatabaseName();").Trim()
    if ($actual -ne $temp) { throw "Từ chối: đang trỏ tới '$actual' thay vì '$temp'." }

    php artisan migrate --force --seed | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'migrate --seed thất bại' }

    # Bảng vận hành / bảo mật: chỉ lấy cấu trúc, không lấy dữ liệu
    $noData = 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
              'activity_logs', 'notifications', 'login_events', 'personal_access_tokens'
    $ignore = ($noData | ForEach-Object { "--ignore-table=$temp.$_" }) -join ' '
    $schema = cmd /c "`"$bin\mysqldump.exe`" -uroot --skip-comments --skip-add-drop-table --no-data --routines $temp"
    $data = cmd /c "`"$bin\mysqldump.exe`" -uroot --skip-comments --no-create-info --insert-ignore --complete-insert $ignore $temp"

    $header = @"
-- ============================================================================
--  TOP CRM — Database schema + dữ liệu khởi tạo
--  Database : orangecrm_db  (MariaDB 10.11 / MySQL 8, InnoDB, utf8mb4)
--  Tạo lúc  : $(Get-Date -Format 'dd/MM/yyyy HH:mm')  bằng scripts/generate-sql.ps1
--
--  NỘI DUNG
--   1. Cấu trúc toàn bộ bảng, khóa chính, khóa ngoại, chỉ mục (tương đương Laravel migrations)
--   2. Dữ liệu cấu hình: tổ chức mặc định, vai trò, quyền, phòng ban, nguồn lead, giai đoạn, danh mục, cài đặt
--   3. DỮ LIỆU DEMO phục vụ kiểm thử — mọi bản ghi demo có cột is_demo = 1
--      (xóa bằng lệnh: php artisan crm:purge-demo)
--   4. Bảng ``migrations`` để Laravel biết cấu trúc đã được tạo (không chạy migrate trùng)
--
--  AN TOÀN
--   - KHÔNG chứa dữ liệu thật của doanh nghiệp (tạo từ database tạm chỉ có dữ liệu demo).
--   - KHÔNG có lệnh DROP. Bảng đã tồn tại thì lệnh tạo bảng được bỏ qua; bản ghi trùng được bỏ qua.
--   - KHÔNG có tài khoản quản trị, KHÔNG có mật khẩu thật, KHÔNG có khóa API / phiên / lịch sử đăng nhập.
--     Tài khoản demo có mật khẩu ngẫu nhiên không thể đoán. Sau khi import, mở /setup để tạo
--     tài khoản quản trị, hoặc chạy: php artisan crm:create-admin
--
--  CÁCH IMPORT: xem docs/DATABASE_SETUP.md
-- ============================================================================

CREATE DATABASE IF NOT EXISTS ``orangecrm_db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ``orangecrm_db``;

"@
    $schemaText = ($schema -join "`n") -replace 'CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `'
    $body = $header + $schemaText + "`n`n-- ---------------------------------------------------------------- DỮ LIỆU`n" + ($data -join "`n") + "`n"
    if ($body -match 'DROP (TABLE|DATABASE)') { throw 'Từ chối: kết quả có lệnh DROP.' }
    [IO.File]::WriteAllText($out, $body, (New-Object Text.UTF8Encoding $false))
    Write-Host "Đã tạo $out ($([math]::Round((Get-Item $out).Length / 1KB)) KB)"
}
finally {
    Remove-Item Env:DB_DATABASE, Env:CRM_DEMO_DATA -ErrorAction SilentlyContinue
    & "$bin\mysql.exe" -uroot -e "DROP DATABASE IF EXISTS $temp;"
    Pop-Location
}
