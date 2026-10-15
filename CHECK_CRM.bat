@echo off
chcp 65001 >nul
setlocal EnableExtensions
title OrangeCRM - Kiểm tra
cd /d "%~dp0"

echo.
echo  ================================================
echo     ORANGECRM - KIỂM TRA TÌNH TRẠNG HỆ THỐNG
echo  ================================================
echo.

set "XAMPP="
for %%D in ("%XAMPP_HOME%" "C:\xampp" "D:\xampp" "E:\xampp") do (
    if not defined XAMPP if exist "%%~D\php\php.exe" set "XAMPP=%%~D"
)
set "PHP="
if defined XAMPP set "PHP=%XAMPP%\php\php.exe"
if not defined PHP for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"
if not defined PHP (echo  [LỖI] Không tìm thấy PHP. & goto :end)

echo  --- Ứng dụng và database ---
"%PHP%" -r "exit(@fsockopen('127.0.0.1',3306)?0:1);" && (echo  [OK]   MySQL đang chạy ^(cổng 3306^)) || (echo  [LỖI]  MySQL KHÔNG chạy - mở XAMPP Control Panel, bấm Start ở MySQL)
"%PHP%" artisan crm:health

echo.
echo  --- Kết nối HTTP ---
for %%U in ("http://127.0.0.1:8000/up" "http://localhost/orangecrm/up") do (
    for /f %%c in ('curl.exe -s -o nul -w "%%{http_code}" %%~U 2^>nul') do (
        if "%%c"=="200" (echo  [OK]   %%~U  ^(HTTP 200^)) else (echo  [--]   %%~U  không phản hồi ^(mã %%c^))
    )
)
echo.
echo  Gợi ý: nếu cả hai địa chỉ không phản hồi, hãy chạy START_CRM.bat

:end
echo.
pause
