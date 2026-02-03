<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Utils\ShortUrlService;

try {
    $service = new ShortUrlService();
    $deleted = $service->cleanupExpired();
    $dbPath = $service->getDbPath();

    echo sprintf("Deleted %d expired short URLs from %s\n", $deleted, $dbPath);
} catch (Throwable $exception) {
    fwrite(STDERR, "Failed to cleanup short URLs.\n");
    exit(1);
}
