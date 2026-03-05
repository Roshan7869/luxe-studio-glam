FROM wordpress:php8.3-apache

# Install required PHP extensions + WP-CLI
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

# Install WP-CLI for cron + maintenance
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Fix Apache FQDN warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy entire project (prevents partial deployment drift)
COPY . /var/www/html

# Install Composer dependencies
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN cd /var/www/html/wp-content/plugins/glamlux-core && composer install --no-dev --optimize-autoloader || true

# Railway-optimized wp-config overwrites default
COPY wp-config-railway.php /var/www/html/wp-config.php

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/wp-content \
    && chown www-data:www-data /var/www/html/wp-config.php \
    && chmod -R 755 /var/www/html/wp-content

# Copy Railway-specific boot entrypoint (dynamic PORT + Apache MPM)
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint.sh
RUN chmod +x /usr/local/bin/railway-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/railway-entrypoint.sh"]
CMD ["apache2-foreground"]
