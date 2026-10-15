@echo off
chcp 65001 >nul
title TOP CRM - Sao lưu
cd /d "%~dp0"
echo.
echo  ================================================
echo     TOP CRM - SAO LƯU DỮ LIỆU
echo     (mã nguồn + cơ sở dữ liệu + tệp tải lên + cấu hình)
echo  ================================================
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\backup.ps1" %*
if errorlevel 1 (
    echo.
    echo  Có cảnh báo - xem chi tiết phía trên hoặc trong C:\TOPCRM_Backup\backup.log
) else (
    echo.
    echo  Xong! Bản sao lưu nằm trong thư mục C:\TOPCRM_Backup
)
echo.
pause