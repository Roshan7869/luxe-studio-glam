#!/bin/bash

#==============================================================================
# GlamLux Database Backup Script
# Purpose: Create encrypted database dumps with S3 upload for disaster recovery
# Usage: ./backup-database.sh [env|dev|test|prod]
#==============================================================================

set -euo pipefail

# Configuration
BACKUP_ENV="${1:-prod}"
BACKUP_DIR="${BACKUP_DIR:-/tmp/glamlux-backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
S3_BUCKET="${S3_BUCKET:-glamlux-backups-prod}"
S3_REGION="${S3_REGION:-us-east-1}"

# Database connection - use wp-cli automatically
DB_NAME=$(wp config get DB_NAME)
DB_USER=$(wp config get DB_USER)
DB_PASSWORD=$(wp config get DB_PASSWORD)
DB_HOST=$(wp config get DB_HOST)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Timestamp for backup file
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/glamlux_${BACKUP_ENV}_${TIMESTAMP}.sql.gz"
BACKUP_FILE_ENCRYPTED="${BACKUP_FILE}.enc"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting database backup for $BACKUP_ENV environment..."

# Execute backup
echo "Dumping database to $BACKUP_FILE..."
mysqldump \
  --single-transaction \
  --quick \
  --lock-tables=false \
  -h "$DB_HOST" \
  -u "$DB_USER" \
  -p"$DB_PASSWORD" \
  "$DB_NAME" | gzip > "$BACKUP_FILE"

if [ -f "$BACKUP_FILE" ]; then
  BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
  echo "✓ Backup created successfully: $BACKUP_SIZE"
else
  echo "✗ Backup failed!"
  exit 1
fi

# Encrypt backup if encryption key is set
if [ -n "${BACKUP_ENCRYPTION_KEY:-}" ]; then
  echo "Encrypting backup with AES-256..."
  openssl enc -aes-256-cbc -salt -in "$BACKUP_FILE" \
    -out "$BACKUP_FILE_ENCRYPTED" \
    -k "$BACKUP_ENCRYPTION_KEY" 2>/dev/null
  
  if [ -f "$BACKUP_FILE_ENCRYPTED" ]; then
    rm "$BACKUP_FILE"
    BACKUP_FILE="$BACKUP_FILE_ENCRYPTED"
    echo "✓ Backup encrypted successfully"
  fi
fi

# Upload to S3 if AWS credentials are configured
if command -v aws &> /dev/null && [ -n "${AWS_ACCESS_KEY_ID:-}" ]; then
  echo "Uploading to S3 bucket: $S3_BUCKET..."
  
  aws s3 cp "$BACKUP_FILE" "s3://$S3_BUCKET/backups/$(basename $BACKUP_FILE)" \
    --region "$S3_REGION" \
    --storage-class STANDARD_IA \
    --sse AES256 \
    --no-progress
  
  if [ $? -eq 0 ]; then
    echo "✓ Backup uploaded to S3"
    # Log backup metadata
    aws s3 cp - "s3://$S3_BUCKET/backups/manifest.json" \
      --region "$S3_REGION" \
      --content-type "application/json" <<EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "environment": "$BACKUP_ENV",
  "file": "$(basename $BACKUP_FILE)",
  "size_bytes": $(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE"),
  "db_name": "$DB_NAME",
  "hostname": "$(hostname)"
}
EOF
  else
    echo "✗ S3 upload failed, keeping local copy"
  fi
else
  echo "⚠ AWS credentials not configured, backup stored locally only"
fi

# Cleanup old local backups (keep RETENTION_DAYS days)
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "glamlux_${BACKUP_ENV}_*.sql.gz*" -mtime +$RETENTION_DAYS -delete

# Verify backup integrity
echo "Verifying backup integrity..."
if gunzip -t "$BACKUP_FILE" 2>/dev/null || [ -f "${BACKUP_FILE%.enc}" ] 2>/dev/null; then
  echo "✓ Backup integrity verified"
else
  echo "⚠ Could not verify backup (may be encrypted)"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup completed successfully!"
echo "Backup file: $(basename $BACKUP_FILE)"
echo "Location: $BACKUP_DIR"

# Exit with success
exit 0
