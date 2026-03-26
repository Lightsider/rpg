FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    bash \
    supervisor \
    nodejs \
    npm \
    rabbitmq-c-dev \
    autoconf \
    build-base \
    linux-headers

# Core PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_pgsql \
    mbstring \
    zip \
    exif \
    pcntl \
    intl \
    sockets

# PECL extensions
RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Update www-data user to match host UID/GID (usually 1000 in WSL2/Linux)
RUN apk add --no-cache shadow \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data

# Setup working directory
WORKDIR /var/www/html

# Add supervisor configuration
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/custom.ini

# Expose port 9000 and start supervisor
EXPOSE 9000 8080 5173
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
