<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Utils\ShortUrlService;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$originalUrl = trim((string)($input['originalUrl'] ?? ''));

if ($originalUrl === '') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Original URL is missing']);
    exit;
}

if (strlen($originalUrl) > 4000) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Original URL is too long']);
    exit;
}

$parsed = parse_url($originalUrl);
$scheme = $parsed['scheme'] ?? '';
if (!filter_var($originalUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Only http/https URLs are allowed']);
    exit;
}

try {
    $service = new ShortUrlService();
    $result = $service->createShortUrl($originalUrl);
    $shortUrl = $service->getBaseUrl() . '/s/' . $result['code'];

    header('Content-Type: application/json');
    echo json_encode([
        'link' => $shortUrl,
        'code' => $result['code'],
        'expires_at' => $result['expires_at'],
    ]);
} catch (Throwable $exception) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to shorten URL']);
}
