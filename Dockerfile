FROM php:8.4-fpm-alpine

# Install system dependencies + nginx + supervisor
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev \
    icu-dev \
    nginx \
    supervisor \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application files
COPY . .

# Complete autoloader (--no-scripts evita que package:discover intente conectar a DB en build time)
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# PHP configuration for production
COPY docker/php/opcache.ini    /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/php/fpm-pool.conf  /usr/local/etc/php-fpm.d/www.conf

# Nginx + supervisor configuration
COPY docker/nginx/nginx.conf        /etc/nginx/nginx.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE ${PORT:-8000}

# startup.sh: injects $PORT into nginx config, then runs migrations and supervisor
CMD ["sh", "-c", "\
    export NGINX_PORT=${PORT:-8000} && \
    sed \"s/NGINX_PORT/$NGINX_PORT/g\" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf && \
    php artisan package:discover --ansi && \
    php artisan migrate --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan storage:link && \
    exec supervisord -c /etc/supervisor/conf.d/supervisord.conf \
"]
