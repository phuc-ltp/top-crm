<?php

/**
 * Công cụ dành cho nhà phát triển: chuyển bản mysqldump thô thành database/sql/orangecrm_db.sql
 * an toàn để import (không DROP, dùng CREATE TABLE IF NOT EXISTS, mã hóa utf8mb4).
 *
 * Cách dùng: php database/sql/build_sql.php <dump_tho.sql> <đầu_ra.sql>
 */
[$script, $input, $output] = $argv + [null, null, null];
if (! $input || ! $output || ! is_file($input)) {
    fwrite(STDERR, "Cách dùng: php build_sql.php <dump.sql> <output.sql>\n");
    exit(1);
}

$sql = file_get_contents($input);

// Bỏ các lệnh phá hủy và lệnh chỉ dành cho dump
$sql = preg_replace('/^DROP TABLE IF EXISTS .*;\R/m', '', $sql);
$sql = preg_replace('/^\/\*M!999999.*\R/m', '', $sql);
$sql = str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $sql);

$header = <<<'SQL'
-- ============================================================================
--  OrangeCRM Enterprise — Database schema + dữ liệu khởi tạo
--  Database : orangecrm_db  (MySQL / MariaDB, InnoDB, utf8mb4)
--  Tạo lúc  : {DATE}
--
--  NỘI DUNG
--   1. Cấu trúc toàn bộ bảng, khóa chính, khóa ngoại, chỉ mục (tương đương Laravel migrations)
--   2. Dữ liệu cấu hình: vai trò, quyền, phòng ban, nguồn lead, giai đoạn pipeline, danh mục, cài đặt
--   3. DỮ LIỆU DEMO phục vụ kiểm thử — mọi bản ghi demo có cột is_demo = 1
--      (xóa bằng lệnh: php artisan crm:purge-demo)
--   4. Bảng `migrations` để Laravel biết cấu trúc đã được tạo (không chạy migrate trùng)
--
--  AN TOÀN
--   - File KHÔNG chứa lệnh DROP DATABASE / DROP TABLE.
--   - Chỉ import vào database TRỐNG. Nếu bảng đã tồn tại, lệnh tạo bảng được bỏ qua.
--   - KHÔNG có tài khoản quản trị và KHÔNG có mật khẩu thật. Tài khoản demo có mật khẩu
--     ngẫu nhiên không thể đoán. Sau khi import, mở trình duyệt vào trang /setup để tự tạo
--     tài khoản Super Admin, hoặc chạy: php artisan crm:create-admin
--
--  CÁCH IMPORT: xem docs/DATABASE_SETUP.md
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `orangecrm_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `orangecrm_db`;

SQL;

$sql = str_replace('{DATE}', date('d/m/Y H:i'), $header).$sql;
file_put_contents($output, $sql);
echo 'Đã tạo '.$output.' ('.number_format(strlen($sql)).' bytes)'.PHP_EOL;
