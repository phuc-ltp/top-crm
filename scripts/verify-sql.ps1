# Kiểm tra file database/sql/orangecrm_db.sql bằng cách nạp vào database TẠM (orangecrm_sqlcheck) 2 lần.
# AN TOÀN: file gốc có "CREATE DATABASE orangecrm_db; USE orangecrm_db;" (để người cài đặt dùng) — script tạo BẢN SAO
# đã đổi tên database sang tên tạm, và TỪ CHỐI chạy nếu bản sao còn bất kỳ chỗ nào trỏ tới orangecrm_db.
# KHÔNG BAO GIỜ nạp file gốc trực tiếp bằng "mysql < orangecrm_db.sql" để thử (sự cố 16/10/2026).
# Cách chạy:  powershell -ExecutionPolicy Bypass -File scripts\verify-sql.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$bin = 'C:\xampp\mysql\bin'
$temp = 'orangecrm_sqlcheck'
$source = Join-Path $root 'database\sql\orangecrm_db.sql'
$copy = Join-Path $env:TEMP "orangecrm_sqlcheck_$PID.sql"

$text = [IO.File]::ReadAllText($source, [Text.Encoding]::UTF8)
$text = $text -replace 'CREATE DATABASE IF NOT EXISTS `orangecrm_db`', "CREATE DATABASE IF NOT EXISTS ``$temp``"
$text = $text -replace 'USE `orangecrm_db`;', "USE ``$temp``;"
if ($text -match 'orangecrm_db`' -or $text -match '(?im)^\s*USE\s+(?!`orangecrm_sqlcheck`)') {
    throw 'Từ chối: bản sao vẫn còn trỏ tới database khác ngoài database tạm.'
}
if ($text -notmatch "USE ``$temp``;") { throw 'Từ chối: không tìm thấy lệnh USE đã đổi tên — định dạng file đã thay đổi, hãy kiểm tra tay.' }
[IO.File]::WriteAllText($copy, $text, (New-Object Text.UTF8Encoding $false))

Push-Location $root
try {
    foreach ($round in 1, 2) {
        & "$bin\mysql.exe" -uroot -h "127.0.0.1" -e "DROP DATABASE IF EXISTS $temp;"
        cmd /c "`"$bin\mysql.exe`" -uroot -h127.0.0.1 < `"$copy`""
        if ($LASTEXITCODE -ne 0) { throw "Nạp lần $round thất bại" }
        Write-Host "  Nạp lần $round vào $temp : OK"
    }
    $q = { param($sql) (& "$bin\mysql.exe" -uroot -h "127.0.0.1" -N $temp -e $sql) }
    $tables = & $q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$temp'"
    $realUsers = & $q 'SELECT COUNT(*) FROM users WHERE is_demo = 0'
    $admins = & $q 'SELECT COUNT(*) FROM users WHERE is_platform_admin = 1'
    $env:DB_DATABASE = $temp
    $actual = (php artisan tinker --execute="echo DB::connection()->getDatabaseName();").Trim()
    if ($actual -ne $temp) { throw "Từ chối: đang trỏ tới '$actual' thay vì '$temp'." }
    $pending = (php artisan migrate:status | Select-String 'Pending').Count
    Write-Host "  Số bảng: $tables · Người dùng thật: $realUsers · Quản trị nền tảng: $admins · Migration còn thiếu: $pending"
    if ([int]$tables -lt 50 -or [int]$realUsers -ne 0 -or [int]$admins -ne 0 -or $pending -ne 0) { throw 'Kiểm tra KHÔNG đạt.' }
    Write-Host '  => File SQL đạt.' -ForegroundColor Green
}
finally {
    Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue
    & "$bin\mysql.exe" -uroot -h "127.0.0.1" -e "DROP DATABASE IF EXISTS $temp;"
    Remove-Item $copy -ErrorAction SilentlyContinue
    Pop-Location
}
