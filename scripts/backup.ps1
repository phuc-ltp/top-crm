# TOP CRM — Sao lưu toàn bộ: mã nguồn (Git), cơ sở dữ liệu, tệp tải lên, cấu hình bí mật.
# Chỉ ĐỌC dữ liệu dự án và GHI vào thư mục sao lưu; không xóa/sửa CSDL hay mã nguồn.
param(
    [string]$BackupRoot = 'C:\TOPCRM_Backup',
    [int]$Keep = 30,            # số bản CSDL giữ lại
    [switch]$NoCommit           # chỉ sao lưu CSDL/tệp, không tạo commit Git
)
$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd_HHmm'
$log = Join-Path $BackupRoot 'backup.log'
$warnings = 0

function Say($msg, $color = 'Gray') { Write-Host "  $msg" -ForegroundColor $color; Add-Content -Path $log -Value "$(Get-Date -Format 's')  $msg" -Encoding UTF8 }
function Warn($msg) { $script:warnings++; Say "[CẢNH BÁO] $msg" 'Yellow' }

foreach ($d in 'database', 'uploads', 'secrets', 'git') { New-Item -ItemType Directory -Force -Path (Join-Path $BackupRoot $d) | Out-Null }
Say "=== Bắt đầu sao lưu $stamp ===" 'Cyan'

# ---------- Đọc cấu hình .env (không in ra màn hình) ----------
$envFile = Join-Path $project '.env'
$cfg = @{}
if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile -Encoding UTF8) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$') { $cfg[$Matches[1]] = $Matches[2].Trim('"', "'") }
    }
} else { Warn 'Không thấy tệp .env' }

# ---------- 1. Cơ sở dữ liệu ----------
$xampp = @($env:XAMPP_HOME, 'C:\xampp', 'D:\xampp') | Where-Object { $_ -and (Test-Path "$_\mysql\bin\mysqldump.exe") } | Select-Object -First 1
$db = if ($cfg.DB_DATABASE) { $cfg.DB_DATABASE } else { 'orangecrm_db' }
if (-not $xampp) {
    Warn 'Không tìm thấy mysqldump (XAMPP) — bỏ qua sao lưu CSDL.'
} else {
    $sql = Join-Path $BackupRoot "database\${db}_$stamp.sql"
    $dumpArgs = @('--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
        "--host=$(if ($cfg.DB_HOST) { $cfg.DB_HOST } else { '127.0.0.1' })", "--port=$(if ($cfg.DB_PORT) { $cfg.DB_PORT } else { '3306' })",
        "--user=$(if ($cfg.DB_USERNAME) { $cfg.DB_USERNAME } else { 'root' })", "--result-file=$sql", $db)
    $env:MYSQL_PWD = $cfg.DB_PASSWORD      # truyền mật khẩu qua biến môi trường, không lộ trên dòng lệnh
    try {
        $p = Start-Process -FilePath "$xampp\mysql\bin\mysqldump.exe" -ArgumentList $dumpArgs -NoNewWindow -Wait -PassThru -RedirectStandardError (Join-Path $env:TEMP 'topcrm_dump_err.txt')
    } finally { Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue }
    if ($p.ExitCode -ne 0 -or -not (Test-Path $sql) -or (Get-Item $sql).Length -lt 1000) {
        Warn "Sao lưu CSDL thất bại (MySQL đã bật chưa?). Chi tiết: $(Get-Content (Join-Path $env:TEMP 'topcrm_dump_err.txt') -Raw)"
        if (Test-Path $sql) { Remove-Item $sql -Force }
    } else {
        $zip = "$sql.zip"
        Compress-Archive -Path $sql -DestinationPath $zip -Force
        Remove-Item $sql -Force
        Say ("CSDL: {0} ({1:N1} MB)" -f (Split-Path $zip -Leaf), ((Get-Item $zip).Length / 1MB)) 'Green'
        Get-ChildItem (Join-Path $BackupRoot 'database') -Filter "${db}_*.sql.zip" | Sort-Object Name -Descending | Select-Object -Skip $Keep | Remove-Item -Force
    }
}

# ---------- 2. Tệp tải lên (logo, ảnh nền, tệp đính kèm) ----------
foreach ($pair in @(@('storage\app\public', 'uploads\public'), @('storage\app\private', 'uploads\private'))) {
    $src = Join-Path $project $pair[0]
    if (Test-Path $src) {
        robocopy $src (Join-Path $BackupRoot $pair[1]) /MIR /NFL /NDL /NJH /NJS /NP /R:1 /W:1 | Out-Null
        if ($LASTEXITCODE -ge 8) { Warn "Sao chép $($pair[0]) lỗi (mã $LASTEXITCODE)" } else { Say "Tệp tải lên: $($pair[0]) → $($pair[1])" 'Green' }
    }
}

# ---------- 3. Cấu hình bí mật (.env chứa APP_KEY — thiếu nó sẽ không giải mã được dữ liệu đã mã hóa) ----------
if (Test-Path $envFile) {
    Copy-Item $envFile (Join-Path $BackupRoot "secrets\env_$stamp.txt") -Force
    Get-ChildItem (Join-Path $BackupRoot 'secrets') -Filter 'env_*.txt' | Sort-Object Name -Descending | Select-Object -Skip 10 | Remove-Item -Force
    Say 'Cấu hình .env đã sao lưu (chỉ lưu trên máy này, KHÔNG đưa lên Git).' 'Green'
}

# ---------- 4. Mã nguồn: commit Git + đẩy sang kho sao lưu ----------
Push-Location $project
$ErrorActionPreference = 'Continue'   # git ghi thông tin ra stderr; không coi đó là lỗi
try {
    $git = (Get-Command git -ErrorAction SilentlyContinue).Source
    if (-not $git) { Warn 'Chưa cài Git — bỏ qua sao lưu mã nguồn.' }
    else {
        $bare = Join-Path $BackupRoot 'git\phuc-crm.git'
        if (-not (Test-Path $bare)) { git init --bare --quiet --initial-branch=main $bare; Say "Tạo kho Git sao lưu: $bare" }
        if (-not (git remote | Select-String -SimpleMatch -Pattern 'backup' -Quiet)) { git remote add backup $bare }

        if (-not $NoCommit) {
            git add -A
            # Chặn an toàn: không bao giờ commit tệp bí mật dù .gitignore bị sửa nhầm
            $danger = git diff --cached --name-only | Where-Object { $_ -match '(^|/)\.env($|\.)' -and $_ -notmatch '\.env\.example$' -or $_ -match '^storage/backups/' -or $_ -match 'settings\.local\.json$' }
            if ($danger) { git reset --quiet; Warn "Phát hiện tệp bí mật sắp bị commit: $($danger -join ', '). Đã hủy commit."; return }
            if (git diff --cached --name-only) {
                git commit --quiet -m "Sao lưu tự động $(Get-Date -Format 'dd/MM/yyyy HH:mm')"
                Say "Đã tạo commit: $(git log -1 --format='%h %s')" 'Green'
            } else { Say 'Mã nguồn không có thay đổi mới.' }
        }
        git push --quiet backup --all
        if ($LASTEXITCODE -eq 0) { Say 'Đã đẩy lịch sử mã nguồn sang kho sao lưu.' 'Green' } else { Warn 'Đẩy sang kho sao lưu thất bại.' }

        if (git remote | Select-String -SimpleMatch -Pattern 'origin' -Quiet) {
            git push --quiet origin --all
            if ($LASTEXITCODE -eq 0) { Say 'Đã đẩy lên kho online (origin).' 'Green' } else { Warn 'Đẩy lên kho online thất bại (kiểm tra mạng / đăng nhập GitHub).' }
        }
    }
} finally { Pop-Location }

if ($warnings) { Say "=== Hoàn tất với $warnings cảnh báo ===" 'Yellow'; exit 1 }
Say "=== Sao lưu hoàn tất. Thư mục: $BackupRoot ===" 'Cyan'
