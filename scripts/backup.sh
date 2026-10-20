#!/usr/bin/env bash
# ==============================================================================
#  Sao lưu TOP CRM trên máy chủ Linux: database (nén) + tệp tải lên + .env. Giữ BACKUP_KEEP_DAYS ngày (mặc định 30).
#  Thủ công:   bash scripts/backup.sh
#  Tự động:    crontab -e  →  15 2 * * * cd /var/www/topcrm && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
#  Nên chép thêm thư mục sao lưu ra nơi khác (máy khác / dịch vụ lưu trữ) — sao lưu chỉ nằm trên cùng máy chủ là chưa đủ an toàn.
# ==============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

BACKUP_DIR="${BACKUP_DIR:-$HOME/topcrm-backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d_%H%M)"
mkdir -p "$BACKUP_DIR/database" "$BACKUP_DIR/uploads" "$BACKUP_DIR/secrets"
chmod 700 "$BACKUP_DIR" "$BACKUP_DIR/secrets"

env_value() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_HOST="$(env_value DB_HOST)"; DB_PORT="$(env_value DB_PORT)"; DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"; DB_PASS="$(env_value DB_PASSWORD)"

# Mật khẩu truyền qua tệp tạm (quyền 600) — không lộ trên dòng lệnh / danh sách tiến trình
CNF="$(mktemp)"; chmod 600 "$CNF"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "${DB_PORT:-3306}" "$DB_USER" "$DB_PASS" > "$CNF"

OUT="$BACKUP_DIR/database/${DB_NAME}_${STAMP}.sql.gz"
mysqldump --defaults-extra-file="$CNF" --single-transaction --routines --default-character-set=utf8mb4 "$DB_NAME" | gzip > "$OUT"
gzip -t "$OUT" && zcat "$OUT" | tail -n 1 | grep -q "Dump completed" || { echo "Bản sao lưu database lỗi: $OUT"; exit 1; }
echo "Database: $OUT"

tar -czf "$BACKUP_DIR/uploads/uploads_${STAMP}.tar.gz" -C storage/app public private 2>/dev/null || true
install -m 600 .env "$BACKUP_DIR/secrets/env_${STAMP}"

find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete
echo "Sao lưu hoàn tất: $BACKUP_DIR"
