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
        'charset' => 'utf8mb4',
    ],

    // Optional WordPress headless CMS URL, e.g. http://localhost/wordpress.
    'cms' => [
        'wordpress_url' => rtrim(getenv('TRADEHUB_WP_URL') ?: '', '/'),
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
