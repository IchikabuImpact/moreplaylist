<?php

declare(strict_types=1);

namespace App;

/**
 * Configure PHP before Composer loads application dependencies.
 *
 * PHP notices must never be written into HTTP response bodies: doing so turns
 * otherwise valid JSON API responses into invalid JSON. Detailed Slim error
 * pages remain available in explicitly selected development environments.
 */
function configureRuntime(string $environment, string $errorLog): bool
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLog);
    error_reporting(E_ALL);

    return in_array(strtolower($environment), ['local', 'development'], true);
}
