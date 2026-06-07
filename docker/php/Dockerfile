FROM php:8.2-fpm

# System dependencies + nginx + supervisor
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source
COPY . .

# Build frontend assets
COPY --from=node:20-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-alpine /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node:20-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN npm run build

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# php-fpm pool config (Unix socket so nginx can connect locally)
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /var/run/php && chown www-data:www-data /var/run/php

# nginx config
COPY docker/nginx/cemetery.conf /etc/nginx/sites-enabled/default
RUN rm -f /etc/nginx/sites-enabled/default.bak \
    && mkdir -p /var/log/supervisor

# supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/cemetery.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/cemetery.conf"]
