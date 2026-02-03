<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Utils\ShortUrlService;

$code = $_GET['code'] ?? '';
if (!is_string($code) || !preg_match('/^[A-Za-z0-9]{6,8}$/', $code)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

try {
    $service = new ShortUrlService();
    $service->cleanupExpired();
    $entry = $service->findActiveByCode($code);

    if (!$entry) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }

    header('Location: ' . $entry['target_url'], true, 302);
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Server Error';
    exit;
}
