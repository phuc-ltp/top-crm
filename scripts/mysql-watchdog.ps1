# Trình giám sát MySQL cho OrangeCRM / TOP CRM
# Chạy ẩn khi START_CRM.bat khởi động; STOP_CRM.bat sẽ tắt nó.
# Mỗi 15 giây kiểm tra cổng 3306; nếu MySQL (XAMPP) bị sập thì tự khởi động lại và ghi nhật ký.
param(
    [Parameter(Mandatory = $true)][string]$Xampp,
    [int]$Interval = 15
)

$log = Join-Path $PSScriptRoot '..\storage\logs\mysql-watchdog.log'
function Write-Log($msg) { Add-Content -Path $log -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg) -Encoding UTF8 }

function Test-MySql {
    $client = New-Object System.Net.Sockets.TcpClient
    try { $iar = $client.BeginConnect('127.0.0.1', 3306, $null, $null); $ok = $iar.AsyncWaitHandle.WaitOne(2000) -and $client.Connected; return $ok }
    catch { return $false } finally { $client.Close() }
}

$mysqld = Join-Path $Xampp 'mysql\bin\mysqld.exe'
$ini = Join-Path $Xampp 'mysql\bin\my.ini'
# Luôn chỉ định tệp log: nếu không, MySQL trên Windows có thể ghi log nhầm vào multi-master.info và không khởi động được
$errLog = Join-Path $Xampp 'mysql\data\mysql_error.log'
Write-Log "Bắt đầu giám sát MySQL ($mysqld)"
$failures = 0

while ($true) {
    if (-not (Test-MySql)) {
        Start-Sleep -Seconds 3
        if (-not (Test-MySql) -and -not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
            $failures++
            Write-Log "MySQL không phản hồi — đang khởi động lại (lần $failures)"
            Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$ini`"", '--standalone', "--log-error=`"$errLog`"" -WindowStyle Hidden
            Start-Sleep -Seconds 8
            if (Test-MySql) { Write-Log 'MySQL đã hoạt động trở lại' } else { Write-Log 'Khởi động lại MySQL thất bại — xem C:\xampp\mysql\data\mysql_error.log' }
        }
    }
    Start-Sleep -Seconds $Interval
}
