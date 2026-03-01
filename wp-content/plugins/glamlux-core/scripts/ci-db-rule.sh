#!/bin/bash
# Phase 3.2: Enforce repository-only DB rule
# Fail build if $wpdb exists outside /repositories/

echo "Checking for illegal \$wpdb usage outside repositories..."

# Find all PHP files in plugin, exclude valid locations
ILLEGAL_USAGE=$(grep -rn "\$wpdb" . \
  --exclude-dir=repositories \
  --exclude-dir=Core \
  --exclude-dir=scripts \
  --exclude=glamlux-core.php \
  --include=\*.php)

if [ -n "$ILLEGAL_USAGE" ]; then
    echo "ERROR: \$wpdb usage detected outside /repositories/ and /Core/!"
    echo "$ILLEGAL_USAGE"
    exit 1
fi

echo "SUCCESS: All DB access confined to repositories."
exit 0
