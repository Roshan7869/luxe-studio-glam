#!/bin/bash
# Database Optimization Script
# Usage: bash scripts/optimize-database.sh

set -e

echo "🔧 GlamLux Database Optimization"
echo "================================="

# Get WordPress path
WP_PATH="${1:-.}"

cd "$WP_PATH"

echo ""
echo "📊 Starting database optimization..."

# Optimize all GlamLux tables
echo ""
echo "1️⃣  Optimizing tables..."
wp db query "OPTIMIZE TABLE wp_glamlux_logs" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_glamlux_alerts" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_glamlux_performance" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_gl_event_queue" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_gl_device_tokens" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_gl_job_queue" 2>/dev/null || true
wp db query "OPTIMIZE TABLE wp_gl_web_push_subscriptions" 2>/dev/null || true

echo "✅ Tables optimized"

# Analyze tables
echo ""
echo "2️⃣  Analyzing tables for query optimizer..."
wp db query "ANALYZE TABLE wp_glamlux_logs" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_glamlux_alerts" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_glamlux_performance" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_gl_event_queue" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_gl_device_tokens" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_gl_job_queue" 2>/dev/null || true
wp db query "ANALYZE TABLE wp_gl_web_push_subscriptions" 2>/dev/null || true

echo "✅ Tables analyzed"

# Check database integrity
echo ""
echo "3️⃣  Checking database integrity..."
wp db check

echo "✅ Database integrity verified"

# Display statistics
echo ""
echo "4️⃣  Database Statistics:"
echo "========================"

TOTAL_SIZE=$(wp db eval 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = DB_NAME();' 2>/dev/null || echo "N/A")
TABLE_COUNT=$(wp db eval 'SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DB_NAME();' 2>/dev/null || echo "N/A")

echo "  📈 Total size: ${TOTAL_SIZE}MB"
echo "  📊 Table count: ${TABLE_COUNT}"

# Cleanup old logs (keep 30 days)
echo ""
echo "5️⃣  Cleaning up old logs (>30 days)..."
wp eval-file wp-content/plugins/glamlux-core/includes/class-glamlux-logger.php << 'EOF'
GlamLux_Logger::cleanup_old_logs(30);
echo "✅ Old logs cleaned";
EOF

# Cleanup old performance metrics (keep 7 days)
echo ""
echo "6️⃣  Cleaning up old performance metrics (>7 days)..."
wp eval-file wp-content/plugins/glamlux-core/includes/class-glamlux-performance.php << 'EOF'
GlamLux_Performance::cleanup_old_metrics(7);
echo "✅ Old metrics cleaned";
EOF

echo ""
echo "✅ Database optimization complete!"
echo "================================="
