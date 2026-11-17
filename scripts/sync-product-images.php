<?php
declare(strict_types=1);

$autoload = getenv('TRADEHUB_COMPOSER_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Composer autoload file was not found: ' . $autoload);
}

require_once $autoload;
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/object-storage.php';

$storage = config()['object_storage'];
if ($storage['bucket'] === '') {
    exit(0);
}

$directory = getenv('TRADEHUB_SEED_IMAGE_DIR') ?: __DIR__ . '/../assets/uploads/products';
if (!is_dir($directory)) {
    throw new RuntimeException('Seed product image directory was not found: ' . $directory);
}

$client = tradehub_image_storage_client();
$finfo = new finfo(FILEINFO_MIME_TYPE);
$synced = 0;

foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
    if (!is_file($file) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['gif', 'jpg', 'jpeg', 'png', 'webp'], true)) {
        continue;
    }

    $key = 'assets/uploads/products/' . basename($file);
    if ($client->doesObjectExistV2($storage['bucket'], $key)) {
        continue;
    }

    $stream = fopen($file, 'rb');
    if ($stream === false) {
        throw new RuntimeException('Could not open seed product image: ' . $file);
    }

    try {
        $client->putObject([
            'Bucket' => $storage['bucket'],
            'Key' => $key,
            'Body' => $stream,
            'ContentType' => $finfo->file($file) ?: 'application/octet-stream',
        ]);
    } finally {
        fclose($stream);
    }
    $synced++;
}

echo sprintf("Synchronized %d seed product image(s).\n", $synced);
