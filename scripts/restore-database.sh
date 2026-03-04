#!/bin/bash

#==============================================================================
# GlamLux Database Restore Script
# Purpose: Restore encrypted database backups from S3 or local storage
# Usage: ./restore-database.sh <backup-file|s3-path> [--verify] [--dry-run]
#==============================================================================

set -euo pipefail

# Configuration
BACKUP_FILE="${1:-}"
S3_BUCKET="${S3_BUCKET:-glamlux-backups-prod}"
S3_REGION="${S3_REGION:-us-east-1}"
RESTORE_DIR="${RESTORE_DIR:-/tmp/glamlux-restore}"
DRY_RUN="${DRY_RUN:-false}"
VERIFY_ONLY="${VERIFY_ONLY:-false}"

# Database connection
DB_NAME=$(wp config get DB_NAME)
DB_USER=$(wp config get DB_USER)
DB_PASSWORD=$(wp config get DB_PASSWORD)
DB_HOST=$(wp config get DB_HOST)

# Validation
if [ -z "$BACKUP_FILE" ]; then
  echo "Usage: $0 <backup-file|s3-path> [--verify] [--dry-run]"
  echo "Examples:"
  echo "  $0 /tmp/backup.sql.gz"
  echo "  $0 s3://glamlux-backups/backups/glamlux_prod_20240101_120000.sql.gz"
  exit 1
fi

# Parse flags
for arg in "$@"; do
  case $arg in
    --verify) VERIFY_ONLY="true" ;;
    --dry-run) DRY_RUN="true" ;;
  esac
done

# Create restore directory
mkdir -p "$RESTORE_DIR"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting database restore process..."

# Function to download from S3
download_from_s3() {
  local s3_path="$1"
  local local_path="$2"
  
  echo "Downloading from S3: $s3_path..."
  aws s3 cp "$s3_path" "$local_path" \
    --region "$S3_REGION" \
    --no-progress
}

# Function to handle encrypted files
decrypt_file() {
  local encrypted_file="$1"
  local output_file="$2"
  
  if [ -z "${BACKUP_ENCRYPTION_KEY:-}" ]; then
    echo "✗ Backup is encrypted but BACKUP_ENCRYPTION_KEY not set"
    exit 1
  fi
  
  echo "Decrypting backup with AES-256..."
  openssl enc -aes-256-cbc -d -in "$encrypted_file" \
    -out "$output_file" \
    -k "$BACKUP_ENCRYPTION_KEY" 2>/dev/null
  
  if [ -f "$output_file" ]; then
    echo "✓ Backup decrypted successfully"
  else
    echo "✗ Decryption failed"
    exit 1
  fi
}

# Determine backup file path
if [[ "$BACKUP_FILE" == s3://* ]]; then
  # Download from S3
  LOCAL_BACKUP="$RESTORE_DIR/$(basename $BACKUP_FILE)"
  download_from_s3 "$BACKUP_FILE" "$LOCAL_BACKUP"
else
  # Use local file
  if [ ! -f "$BACKUP_FILE" ]; then
    echo "✗ Backup file not found: $BACKUP_FILE"
    exit 1
  fi
  LOCAL_BACKUP="$BACKUP_FILE"
fi

# Handle encrypted backups
if [[ "$LOCAL_BACKUP" == *.enc ]]; then
  DECRYPTED_BACKUP="${LOCAL_BACKUP%.enc}"
  decrypt_file "$LOCAL_BACKUP" "$DECRYPTED_BACKUP"
  LOCAL_BACKUP="$DECRYPTED_BACKUP"
fi

# Verify backup integrity
echo "Verifying backup file..."
if ! gunzip -t "$LOCAL_BACKUP" 2>/dev/null; then
  echo "✗ Backup file is corrupted or invalid"
  exit 1
fi
echo "✓ Backup file is valid"

if [ "$VERIFY_ONLY" = "true" ]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Verification complete. Backup is healthy."
  exit 0
fi

# Create backup of current database before restore
echo "Creating safety backup of current database..."
SAFETY_BACKUP="$RESTORE_DIR/glamlux_${DB_NAME}_pre_restore_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump \
  --single-transaction \
  --quick \
  --lock-tables=false \
  -h "$DB_HOST" \
  -u "$DB_USER" \
  -p"$DB_PASSWORD" \
  "$DB_NAME" | gzip > "$SAFETY_BACKUP"
echo "✓ Safety backup created: $SAFETY_BACKUP"

if [ "$DRY_RUN" = "true" ]; then
  echo "[DRY RUN MODE] Would restore from: $LOCAL_BACKUP"
  echo "[DRY RUN MODE] Would drop and recreate database: $DB_NAME"
  exit 0
fi

# Confirm before restore
read -p "⚠ This will replace all data in $DB_NAME. Continue? (yes/no) " -r CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy][Ee][Ss]$ ]]; then
  echo "Restore cancelled."
  exit 1
fi

# Restore database
echo "Restoring database from backup..."
gunzip -c "$LOCAL_BACKUP" | mysql \
  -h "$DB_HOST" \
  -u "$DB_USER" \
  -p"$DB_PASSWORD" \
  "$DB_NAME"

if [ $? -eq 0 ]; then
  echo "✓ Database restored successfully"
  
  # Post-restore verification
  echo "Running post-restore verification..."
  TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" | tail -1)
  
  echo "✓ Restored database has $TABLE_COUNT tables"
  
  # Run WordPress database health check
  wp db check
  
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restore completed successfully!"
  echo "Safety backup: $SAFETY_BACKUP"
else
  echo "✗ Database restore failed!"
  echo "Emergency restore from safety backup: mysql < $SAFETY_BACKUP"
  exit 1
fi

exit 0
