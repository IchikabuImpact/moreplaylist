<?php
require __DIR__ . '/../src/runtime.php';

$displayErrorDetails = App\configureRuntime(
    $_SERVER['APPLICATION_ENV'] ?? 'production',
    __DIR__ . '/../logs/error.log'
);

ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\ErrorMiddleware;
use Slim\Views\PhpRenderer;
use App\Utils\GoogleClientFactory;

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();
$app->setBasePath('');

// ビューの設定
$container->set('view', function() {
    return new PhpRenderer(__DIR__ . '/../application/views');
});

// Google Client の設定
$container->set('googleClient', function() {
    $factory = new GoogleClientFactory();
    return $factory->create();
});

// ミドルウェアの追加
$app->addRoutingMiddleware();
$errorMiddleware = new ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    $displayErrorDetails,
    true,
    true
);
$app->add($errorMiddleware);

// ルーティングの追加
require __DIR__ . '/../src/routes.php';

$app->run();
