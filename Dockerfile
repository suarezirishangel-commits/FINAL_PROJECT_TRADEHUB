FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" curl mbstring opcache pdo_mysql simplexml xml zip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

COPY admin ./admin
COPY assets/css ./assets/css
COPY assets/js ./assets/js
COPY assets/uploads/products ./assets/uploads/products
COPY config ./config
COPY includes ./includes
COPY *.php ./

COPY scripts /opt/tradehub/scripts
COPY assets/uploads/products /opt/tradehub/assets/uploads/products
COPY config /opt/tradehub/config
COPY includes /opt/tradehub/includes
COPY docker/root-apache.conf /etc/apache2/conf-available/tradehub.conf
COPY docker/entrypoint.sh /usr/local/bin/tradehub-entrypoint

RUN a2enmod rewrite headers \
    && a2enconf tradehub \
    && mkdir -p logs \
    && chown -R www-data:www-data logs assets/uploads/products \
    && chmod +x /usr/local/bin/tradehub-entrypoint

EXPOSE 10000
CMD ["/usr/local/bin/tradehub-entrypoint"]
