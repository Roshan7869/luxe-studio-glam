#!/bin/bash

echo "🚀 Installing GlamLux2Lux..."

# Update server
sudo apt update -y

# Install Docker
if ! command -v docker &> /dev/null
then
    curl -fsSL https://get.docker.com | sh
fi

# Install Docker Compose
sudo apt install docker-compose -y

# Start containers
docker compose up -d --build

# Wait for WordPress
sleep 15

# Install WordPress automatically
ADMIN_PASS=$(openssl rand -base64 12)
echo "⚠️  IMPORTANT: Your WP admin password is: $ADMIN_PASS  — change this after first login!"
docker exec glamlux_wp wp core install \
    --url="http://localhost" \
    --title="GlamLux2Lux" \
    --admin_user="admin" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="admin@glamlux.com" \
    --allow-root

# Activate Custom Theme
docker exec glamlux_wp wp theme activate glamlux-theme --allow-root

# Install Redis Object Cache
docker exec glamlux_wp wp plugin install redis-cache --activate --allow-root
docker exec glamlux_wp wp redis enable --allow-root

# Disable WP-Cron
docker exec glamlux_wp wp config set DISABLE_WP_CRON true --allow-root

# Setup system cron
(crontab -l 2>/dev/null; echo "*/5 * * * * docker exec glamlux_wp wp cron event run --due-now --allow-root") | crontab -

echo "✅ GlamLux2Lux is live!"
