<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/object-storage.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/paypal.php';

date_default_timezone_set('Asia/Manila');

// Application base path (supports both a WAMP sub-folder and a domain root).
if (!defined('APP_BASE')) {
    $root    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docroot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base    = ($docroot !== '' && str_starts_with($root, $docroot)) ? substr($root, strlen($docroot)) : '';
    define('APP_BASE', rtrim($base, '/'));
}

// Secure session handling.
if (session_status() === PHP_SESSION_NONE) {
    session_name('TRADEHUBSESSID');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => APP_BASE === '' ? '/' : APP_BASE . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    session_start();
}

// Regenerate the ID periodically to limit fixation/session theft.
if (empty($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} elseif (time() - $_SESSION['created_at'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}
