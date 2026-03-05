#!/bin/bash
set -e

# Railway injects a dynamic $PORT — Apache must listen on it
PORT="${PORT:-80}"

echo "==> Configuring Apache to listen on port $PORT"

# Clean up any duplicate MPMs that might cause the 'More than one MPM loaded' error
# The official image sometimes leaves mpm_event or mpm_worker enabled when we want mpm_prefork (used by PHP)
a2dismod mpm_event mpm_worker || true
a2enmod mpm_prefork || true

# Rewrite Apache ports.conf cleanly
cat > /etc/apache2/ports.conf <<EOF
Listen ${PORT}

<IfModule ssl_module>
    Listen 443
</IfModule>

<IfModule mod_gnutls.c>
    Listen 443
</IfModule>
EOF

# Update VirtualHost port in 000-default.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "==> Apache configured for port $PORT"

# ── Self-Healing: Clean stale duplicate files on persistent volume ──────────
# Railway's persistent volume retains old files across deployments.
# Remove known duplicate/deprecated files BEFORE WordPress copies new files.
echo "==> Cleaning stale plugin files from persistent volume..."
rm -f /var/www/html/wp-content/plugins/glamlux-core/includes/class-glamlux-logger.php 2>/dev/null || true
rm -rf /var/www/html/glam_zip_1 2>/dev/null || true
echo "==> Stale file cleanup complete."

# Now hand off to the official WordPress docker-entrypoint.sh, which sets up wp-config.php
exec docker-entrypoint.sh "$@"
