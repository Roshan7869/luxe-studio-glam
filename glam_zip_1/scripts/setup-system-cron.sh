#!/bin/bash
# ==============================================================================
# GlamLux2Lux — System Cron Setup (Phase 5: Concurrency)
# ==============================================================================
#
# PURPOSE:
#   Replaces WordPress pseudo-cron (triggered by page visits) with
#   a real OS-level cron job running every 5 minutes.
#
#   WHY: WP-Cron fires only when a user visits the site. Under low traffic
#   the payroll/membership jobs may be delayed by hours. Under high traffic,
#   every single request adds cron overhead to the response time.
#
# SETUP:
#   1. Add DISABLE_WP_CRON=true to wp-config.php (done via docker-compose)
#   2. Run this script as root to install the OS cron job:
#      chmod +x scripts/setup-system-cron.sh && sudo ./scripts/setup-system-cron.sh
#
# REQUIREMENTS:
#   - WP-CLI installed at /usr/local/bin/wp
#   - ABSPATH points to the WordPress installation
# ==============================================================================

set -euo pipefail

WP_PATH="${1:-/var/www/html}"
CRON_USER="${2:-www-data}"
WP_CLI="/usr/local/bin/wp"

# ── Step 1: Verify WP-CLI is available ───────────────────────────────────────
if ! command -v "$WP_CLI" &>/dev/null; then
    echo "❌ WP-CLI not found at $WP_CLI"
    echo "   Install it: curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/utils/install.sh | bash"
    exit 1
fi

echo "✅ WP-CLI found: $($WP_CLI --info --allow-root | grep 'WP-CLI version')"

# ── Step 2: Verify DISABLE_WP_CRON is set ────────────────────────────────────
DISABLE_CHECK=$(wp --path="$WP_PATH" --allow-root eval "echo (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'yes' : 'no';" 2>/dev/null || echo "no")

if [ "$DISABLE_CHECK" != "yes" ]; then
    echo ""
    echo "⚠️  WARNING: DISABLE_WP_CRON is NOT set to true in wp-config.php"
    echo "   Add this to your wp-config.php or docker-compose environment:"
    echo "   define('DISABLE_WP_CRON', true);"
    echo ""
    echo "   For Docker, add to WORDPRESS_CONFIG_EXTRA in docker-compose.yml:"
    echo "   define('DISABLE_WP_CRON', true);"
    echo ""
fi

# ── Step 3: Install system cron entry ────────────────────────────────────────
CRON_JOB="*/5 * * * * $CRON_USER $WP_CLI --path=$WP_PATH cron event run --due-now --allow-root >> /var/log/glamlux-cron.log 2>&1"
CRON_FILE="/etc/cron.d/glamlux-cron"

cat > "$CRON_FILE" <<EOF
# GlamLux System Cron — installed by setup-system-cron.sh
# Runs WP-Cron every 5 minutes via WP-CLI (replaces pseudo-cron)
# DO NOT EDIT — regenerate by running setup-system-cron.sh

SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

$CRON_JOB
EOF

chmod 644 "$CRON_FILE"

echo ""
echo "✅ System cron installed at: $CRON_FILE"
echo "   Schedule: every 5 minutes as user: $CRON_USER"

# ── Step 4: Create log file ───────────────────────────────────────────────────
touch /var/log/glamlux-cron.log
chown "$CRON_USER":"$CRON_USER" /var/log/glamlux-cron.log
echo "✅ Log file created: /var/log/glamlux-cron.log"

# ── Step 5: Verify next scheduled events ─────────────────────────────────────
echo ""
echo "📋 Upcoming WP-Cron events:"
wp --path="$WP_PATH" --allow-root cron event list --fields=hook,next_run_gmt,schedule 2>/dev/null || echo "  (run inside Docker container for live output)"

echo ""
echo "══════════════════════════════════════════════════════"
echo "  GlamLux System Cron Setup Complete"
echo "  Next cron run: within 5 minutes"
echo "  Monitor: tail -f /var/log/glamlux-cron.log"
echo "══════════════════════════════════════════════════════"
