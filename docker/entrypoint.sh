#!/bin/sh
set -eu

if [ -n "${AWS_BUCKET:-}" ]; then
    TRADEHUB_COMPOSER_AUTOLOAD=/var/www/html/vendor/autoload.php \
        TRADEHUB_SEED_IMAGE_DIR=/opt/tradehub/assets/uploads/products \
        php /opt/tradehub/scripts/sync-product-images.php
fi

port="${PORT:-10000}"
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s#<VirtualHost \\*:80>#<VirtualHost *:${port}>#" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
