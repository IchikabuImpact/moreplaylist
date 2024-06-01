<?php
// エラーレポートをすべて表示する
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
use Google\Client;

error_reporting(E_ALL);

// ログをファイルに出力する設定
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

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
    $client = new Client();
    $client->setAuthConfig(__DIR__ . '/../client_secret.json'); // client_secret.jsonのパスを設定
    $client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/Index/oauth');
    $client->setScopes([
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.force-ssl',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]);
    $client->setAccessType('offline');
    $client->setApprovalPrompt("force");
    $client->setIncludeGrantedScopes(true);
    return $client;
});

// ミドルウェアの追加
$app->addRoutingMiddleware();
$errorMiddleware = new ErrorMiddleware($app->getCallableResolver(), $app->getResponseFactory(), true, true, true);
$app->add($errorMiddleware);

// ルーティングの追加
require __DIR__ . '/../src/routes.php';

$app->run();

