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
docker exec glamlux_wp wp core install \
    --url="http://localhost" \
    --title="GlamLux2Lux" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@glamlux.com" \
    --allow-root

# Install Redis Object Cache
docker exec glamlux_wp wp plugin install redis-cache --activate --allow-root
docker exec glamlux_wp wp redis enable --allow-root

# Disable WP-Cron
docker exec glamlux_wp wp config set DISABLE_WP_CRON true --allow-root

# Setup system cron
(crontab -l 2>/dev/null; echo "*/5 * * * * docker exec glamlux_wp wp cron event run --due-now --allow-root") | crontab -

echo "✅ GlamLux2Lux is live!"
