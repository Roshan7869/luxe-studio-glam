FROM wordpress:php8.2-fpm

# Install dependencies required for WP-CLI and standard WordPress operations
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    less \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# The standard wordpress image already includes mysqli, but we ensure it's there
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Ensure www-data owns the document root
RUN chown -R www-data:www-data /var/www/html
