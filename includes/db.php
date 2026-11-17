<?php
declare(strict_types=1);

/** Shared PDO connection. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config/config.php';
    $db  = $cfg['db'];

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);

    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if ($db['ssl_ca'] !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $db['ssl_ca'];
        }

        $pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('TradeHub DB connection failed: ' . $e->getMessage());
        exit('Database connection failed. Check config/config.php and make sure MySQL is running.');
    }

    return $pdo;
}
