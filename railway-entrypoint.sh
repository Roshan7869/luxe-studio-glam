#!/bin/bash
set -e

# Railway injects a dynamic $PORT — Apache must listen on it
PORT="${PORT:-80}"

echo "==> Configuring Apache to listen on port $PORT"

# Rewrite Apache ports.conf
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
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "==> Apache configured for port $PORT"

# Now hand off to the official WordPress docker-entrypoint.sh, which sets up wp-config.php
exec docker-entrypoint.sh "$@"
