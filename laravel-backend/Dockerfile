FROM php:8.3-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath curl mbstring opcache pdo_mysql simplexml xml zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY laravel-backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

FROM node:22-alpine AS assets

WORKDIR /app
COPY laravel-backend/package*.json ./
RUN npm ci
COPY laravel-backend/ ./
RUN npm run build

FROM php-base

WORKDIR /var/www/html
COPY --from=vendor /var/www/html ./
COPY --from=assets /app/public/build ./public/build
COPY assets/uploads/products ./public/assets/uploads/products
COPY scripts /opt/tradehub/scripts
COPY assets/uploads/products /opt/tradehub/assets/uploads/products
COPY config /opt/tradehub/config
COPY includes /opt/tradehub/includes
COPY docker/entrypoint.sh /usr/local/bin/tradehub-entrypoint

RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/tradehub.conf \
    && a2enmod rewrite headers \
    && a2enconf tradehub \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/tradehub-entrypoint

EXPOSE 10000
CMD ["/usr/local/bin/tradehub-entrypoint"]
