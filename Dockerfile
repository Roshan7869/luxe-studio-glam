FROM wordpress:apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Fix Apache "Could not reliably determine server's FQDN" warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy custom wp-content (themes + plugins)
COPY wp-content /var/www/html/wp-content

# Copy our Railway-optimized wp-config (reads MYSQLHOST / WORDPRESS_DB_* safely)
COPY wp-config-railway.php /var/www/html/wp-config.php

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/wp-content \
    && chown www-data:www-data /var/www/html/wp-config.php \
    && chmod -R 755 /var/www/html/wp-content

# Copy Railway-specific boot entrypoint (fixes dynamic PORT + Apache MPM)
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint.sh
RUN chmod +x /usr/local/bin/railway-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
CMD ["apache2-foreground"]
