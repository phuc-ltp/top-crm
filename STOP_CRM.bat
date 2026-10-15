@echo off
chcp 65001 >nul
setlocal EnableExtensions
title OrangeCRM - Dừng
cd /d "%~dp0"
set "PIDFILE=%~dp0storage\framework\crm-processes.pid"

echo.
echo  ================================================
echo     ORANGECRM - DỪNG MÁY CHỦ ỨNG DỤNG
echo  ================================================
echo  Chỉ dừng máy chủ Laravel, bộ nhắc việc và trình giám sát MySQL của CRM.
echo  KHÔNG tắt Apache, MySQL hay chương trình khác.
echo.

rem Dừng đúng các tiến trình đã ghi PID khi khởi động (kèm tiến trình con)
powershell -NoProfile -Command ^
  "$stopped = 0; $root = (Resolve-Path '%~dp0.').Path;" ^
  "if (Test-Path '%PIDFILE%') { foreach ($id in Get-Content '%PIDFILE%') { $p = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int]$id) -ErrorAction SilentlyContinue; if ($p -and $p.Name -eq 'php.exe' -and $p.CommandLine -match 'artisan') { & taskkill.exe /PID $id /T /F | Out-Null; $stopped++ } elseif ($p -and $p.Name -eq 'powershell.exe' -and $p.CommandLine -match 'mysql-watchdog') { Stop-Process -Id $id -Force; $stopped++ } }; Remove-Item '%PIDFILE%' -Force };" ^
  "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { $_.CommandLine -like ('*' + $root + '*vendor*server.php*') } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force; $stopped++ };" ^
  "if ($stopped -gt 0) { Write-Host '  [OK]  Đã dừng máy chủ OrangeCRM.' } else { Write-Host '  [i]   Không có máy chủ OrangeCRM nào đang chạy.' }"

echo.
echo  Lưu ý: nếu bạn dùng địa chỉ http://localhost/orangecrm (Apache),
echo  hãy bấm Stop ở dòng Apache trong XAMPP Control Panel nếu muốn tắt hẳn.
echo.
timeout /t 6 >nul
