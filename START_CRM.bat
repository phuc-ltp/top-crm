@echo off
chcp 65001 >nul
setlocal EnableExtensions
title OrangeCRM - Khởi động
cd /d "%~dp0"
set "PORT=8000"
set "PIDFILE=%~dp0storage\framework\crm-processes.pid"

echo.
echo  ================================================
echo     ORANGECRM ENTERPRISE - KHỞI ĐỘNG HỆ THỐNG
echo  ================================================
echo.

rem ---------- 1. Tìm XAMPP và PHP (không cố định đường dẫn) ----------
set "XAMPP="
for %%D in ("%XAMPP_HOME%" "C:\xampp" "D:\xampp" "E:\xampp") do (
    if not defined XAMPP if exist "%%~D\php\php.exe" set "XAMPP=%%~D"
)
set "PHP="
if defined XAMPP set "PHP=%XAMPP%\php\php.exe"
if not defined PHP for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"
if not defined PHP (
    echo  [LỖI] Không tìm thấy PHP. Hãy cài XAMPP vào C:\xampp hoặc đặt biến XAMPP_HOME.
    goto :fail
)
echo  [OK]  PHP: %PHP%

rem ---------- 2. Thư viện và giao diện ----------
if not exist "vendor\autoload.php" (
    echo  [..]  Chưa có thư viện PHP, đang chạy composer install...
    where composer >nul 2>&1 || (echo  [LỖI] Chưa cài Composer - xem docs\INSTALLATION.md & goto :fail)
    call composer install --no-interaction || (echo  [LỖI] composer install thất bại. & goto :fail)
)
if not exist "public\build\manifest.json" (
    echo  [..]  Chưa build giao diện, đang chạy npm...
    where npm >nul 2>&1 || (echo  [LỖI] Chưa cài Node.js/npm - xem docs\INSTALLATION.md & goto :fail)
    call npm install --no-audit --no-fund && call npm run build || (echo  [LỖI] Build giao diện thất bại. & goto :fail)
)
if not exist ".env" (
    echo  [LỖI] Thiếu tệp .env. Sao chép .env.example thành .env rồi chạy lại - xem docs\INSTALLATION.md
    goto :fail
)

rem ---------- 3. MySQL ----------
"%PHP%" -r "exit(@fsockopen('127.0.0.1',3306)?0:1);"
if errorlevel 1 (
    echo  [..]  MySQL chưa chạy, đang khởi động MySQL của XAMPP...
    if not defined XAMPP (echo  [LỖI] Không tìm thấy XAMPP để khởi động MySQL. Hãy bật MySQL thủ công. & goto :fail)
    start "OrangeCRM MySQL" /min "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP%\mysql\bin\my.ini" --standalone --log-error="%XAMPP%\mysql\data\mysql_error.log"
    for /l %%i in (1,1,20) do (
        "%PHP%" -r "exit(@fsockopen('127.0.0.1',3306)?0:1);" && goto :mysql_ready
        timeout /t 1 /nobreak >nul
    )
    echo  [LỖI] MySQL không khởi động được. Mở XAMPP Control Panel, bấm Start ở dòng MySQL rồi chạy lại.
    goto :fail
)
:mysql_ready
echo  [OK]  MySQL đang chạy (cổng 3306)

rem ---------- 4. Database (an toàn, không xóa dữ liệu) ----------
"%PHP%" artisan crm:install --no-interaction >nul 2>&1
"%PHP%" artisan crm:health
if errorlevel 1 (
    echo  [LỖI] Kiểm tra hệ thống thất bại. Xem chi tiết phía trên và docs\TROUBLESHOOTING.md
    goto :fail
)

rem ---------- 5. Máy chủ ứng dụng ----------
set "APACHE_URL="
for /f %%c in ('curl.exe -s -o nul -w "%%{http_code}" http://localhost/orangecrm/up 2^>nul') do if "%%c"=="200" set "APACHE_URL=http://localhost/orangecrm"

set "SERVE_URL=http://127.0.0.1:%PORT%"
"%PHP%" -r "exit(@fsockopen('127.0.0.1',%PORT%)?0:1);"
if errorlevel 1 (
    echo  [..]  Đang khởi động máy chủ Laravel tại %SERVE_URL% ...
    powershell -NoProfile -Command "$p1 = Start-Process -FilePath '%PHP%' -ArgumentList 'artisan','serve','--host=127.0.0.1','--port=%PORT%' -WorkingDirectory '%~dp0.' -WindowStyle Minimized -PassThru; $p2 = Start-Process -FilePath '%PHP%' -ArgumentList 'artisan','schedule:work' -WorkingDirectory '%~dp0.' -WindowStyle Minimized -PassThru; Set-Content -Path '%PIDFILE%' -Value @($p1.Id, $p2.Id)"
    for /l %%i in (1,1,15) do (
        curl.exe -s -o nul -f %SERVE_URL%/up && goto :serve_ready
        timeout /t 1 /nobreak >nul
    )
    echo  [LỖI] Máy chủ Laravel không phản hồi tại %SERVE_URL%. Cổng %PORT% có thể bị phần mềm khác chiếm.
    goto :fail
) else (
    curl.exe -s -o nul -f %SERVE_URL%/up || (echo  [LỖI] Cổng %PORT% đang bị chương trình khác sử dụng. & goto :fail)
    echo  [OK]  Máy chủ Laravel đã chạy sẵn.
)
:serve_ready

rem ---------- 6. Trình giám sát MySQL (tự khởi động lại nếu MySQL bị sập) ----------
if defined XAMPP (
    powershell -NoProfile -Command "$running = Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'powershell.exe' -and $_.ProcessId -ne $PID -and $_.CommandLine -like '*mysql-watchdog.ps1*' }; if (-not $running) { $w = Start-Process powershell -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-File','%~dp0scripts\mysql-watchdog.ps1','-Xampp','%XAMPP%' -WindowStyle Hidden -PassThru; Add-Content -Path '%PIDFILE%' -Value $w.Id }"
    echo  [OK]  Đã bật giám sát MySQL ^(tự khởi động lại nếu bị sập^)
)

set "OPEN_URL=%SERVE_URL%"
if defined APACHE_URL set "OPEN_URL=%APACHE_URL%"

echo.
echo  ================================================
echo    ORANGECRM ĐÃ SẴN SÀNG
if defined APACHE_URL echo    Qua Apache XAMPP : %APACHE_URL%
echo    Qua Laravel      : %SERVE_URL%
echo  ------------------------------------------------
echo    Lần đầu sử dụng: trình duyệt sẽ mở trang tạo
echo    tài khoản quản trị - hãy tự đặt mật khẩu.
echo    Để tắt: chạy STOP_CRM.bat
echo  ================================================
echo.
start "" "%OPEN_URL%"
timeout /t 8 >nul
exit /b 0

:fail
echo.
echo  Khởi động KHÔNG thành công. Nhấn phím bất kỳ để đóng.
pause >nul
exit /b 1
