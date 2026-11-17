<?php
declare(strict_types=1);

use Aws\S3\S3Client;

function tradehub_image_storage_client(): S3Client
{
    static $client = null;
    if ($client instanceof S3Client) {
        return $client;
    }

    $storage = config()['object_storage'];
    foreach (['bucket', 'key', 'secret', 'url'] as $required) {
        if ($storage[$required] === '') {
            throw new RuntimeException('AWS_BUCKET, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_URL are required for shared product-image storage.');
        }
    }

    $options = [
        'version' => 'latest',
        'region' => $storage['region'],
        'credentials' => [
            'key' => $storage['key'],
            'secret' => $storage['secret'],
        ],
        'use_path_style_endpoint' => $storage['use_path_style_endpoint'],
    ];
    if ($storage['endpoint'] !== '') {
        $options['endpoint'] = $storage['endpoint'];
    }

    return $client = new S3Client($options);
}
