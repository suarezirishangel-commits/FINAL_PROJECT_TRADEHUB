<?php
/**
 * Application configuration.
 * Values can be overridden with environment variables.
 */

return [
    'db' => [
        'host'    => getenv('TRADEHUB_DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('TRADEHUB_DB_PORT') ?: '3306',
       'name'    => getenv('TRADEHUB_DB_NAME') ?: 'tradehub',
        'user'    => getenv('TRADEHUB_DB_USER') ?: 'root',
        'pass'    => getenv('TRADEHUB_DB_PASS') ?: '',
       'ssl_ca'  => getenv('TRADEHUB_DB_SSL_CA') ?: '',
        'charset' => 'utf8mb4',
    ],

    // Optional WordPress headless CMS URL, e.g. http://localhost/wordpress.
    'cms' => [
        'wordpress_url' => rtrim(getenv('TRADEHUB_WP_URL') ?: '', '/'),
    ],

    'paypal' => [
        'mode' => getenv('TRADEHUB_PAYPAL_MODE') ?: 'sandbox',
        'client_id' => getenv('TRADEHUB_PAYPAL_CLIENT_ID') ?: '',
        'client_secret' => getenv('TRADEHUB_PAYPAL_CLIENT_SECRET') ?: '',
        'currency' => getenv('TRADEHUB_PAYPAL_CURRENCY') ?: 'PHP',
    ],

    'object_storage' => [
        'bucket' => getenv('AWS_BUCKET') ?: '',
        'region' => getenv('AWS_DEFAULT_REGION') ?: 'auto',
        'endpoint' => getenv('AWS_ENDPOINT') ?: '',
        'url' => rtrim(getenv('AWS_URL') ?: '', '/'),
        'key' => getenv('AWS_ACCESS_KEY_ID') ?: '',
        'secret' => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
        'use_path_style_endpoint' => filter_var(
            getenv('AWS_USE_PATH_STYLE_ENDPOINT') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    // Gmail SMTP integration for order confirmations.
    // For Gmail use smtp.gmail.com:587 and an App Password.
    'smtp' => [
        'host'       => getenv('TRADEHUB_SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => (int) (getenv('TRADEHUB_SMTP_PORT') ?: 587),
        'username'   => getenv('TRADEHUB_SMTP_USER') ?: '',
        'password'   => getenv('TRADEHUB_SMTP_PASS') ?: '',
        'encryption' => getenv('TRADEHUB_SMTP_SECURE') ?: 'tls',
        'from_email' => getenv('TRADEHUB_MAIL_FROM') ?: 'no-reply@tradehub.test',
        'from_name'  => 'TradeHub',
    ],
];
