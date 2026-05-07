# ─── Stage 1: PHP Dependencies ───────────────────────
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Install only production dependencies
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    --apcu-autoloader \
    --ignore-platform-reqs

# Copy application code
COPY . .
# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# ─── Stage 2: Frontend Assets ───────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
# Copy package files first for better caching
COPY package*.json vite.config.* ./
RUN npm ci
# Copy source files
COPY resources/ resources/
COPY public/ public/
# Build assets
RUN npm run build

# ─── Stage 3: Production Image ─────────────────────────
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    autoconf \
    build-base \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libwebp-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    linux-headers \
    openssl-dev \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-webp --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl

# Install MongoDB and Redis via PECL (Alpine packages don't work with Docker official PHP)
RUN pecl install mongodb && docker-php-ext-enable mongodb
RUN printf "\n" | pecl install redis && docker-php-ext-enable redis

# PHP configuration
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Copy optimized vendor and application
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app .

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# OPcache configuration
RUN echo "opcache.memory_consumption=256" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Ensure storage directory structure exists
RUN mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/storage/framework/cache/data \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/testing \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/bootstrap/cache

# Set permissions
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/build \
    && chmod -R 775 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Copy and prepare entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
