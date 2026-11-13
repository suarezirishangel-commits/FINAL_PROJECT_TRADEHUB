<?php
/**
 * Minimal PSR-4 autoloader for the manually vendored PHPMailer package.
 * Composer's vendor folder could not be generated on this host, so the
 * library is loaded directly from vendor/phpmailer/phpmailer/src.
 */

spl_autoload_register(static function (string $class): void {
    $prefix  = 'PHPMailer\\PHPMailer\\';
    $baseDir = __DIR__ . '/phpmailer/phpmailer/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = $baseDir . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
