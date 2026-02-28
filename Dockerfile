FROM wordpress:apache

# Install required extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo_mysql

# Copy custom wp-content
COPY wp-content /var/www/html/wp-content

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/wp-content

EXPOSE 80
