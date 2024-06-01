<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteCollectorProxy;
use Google\Client;
use Google\Service\Oauth2;
use Slim\Exception\HttpNotFoundException;
use App\Utils\SessionManager;
use App\Utils\LogManager;

// セッション管理とログ管理のインスタンス化
$session = new SessionManager();
$logManager = new LogManager();
$logger = $logManager->getLogger();

// 許可されたURIのリスト
$allowedUris = [
    '/',
    '/Index',
    '/logout',
    '/Index/oauth',
    '/Index/share',
    '/csrf-token',
    '/api/videos',
    '/api/playlists',
    '/api/playlist-videos',
    '/api/check-login',
    '/api/generate-share-url',
    '/api/add-playlist',
    '/api/add-to-existing-playlist',
];

// URIが許可されているかをチェックするミドルウェア
$uriCheckMiddleware = function (Request $request, RequestHandlerInterface $handler) use ($allowedUris, $app, $logger) {
    $uri = $request->getUri()->getPath();
    if (!in_array($uri, $allowedUris)) {
        $response = new \Slim\Psr7\Response();
        $view = $app->getContainer()->get('view');
        $logger->warning("Access to unauthorized URI: $uri");
        return $view->render($response->withStatus(404), '404.phtml');
    }
    return $handler->handle($request);
};

// ミドルウェアを追加
$app->add($uriCheckMiddleware);

// 共通のAPIルート設定関数
function setApiRoutes(RouteCollectorProxy $group, SessionManager $session, LogManager $logManager) {
    $logger = $logManager->getLogger();
    $group->map(['GET', 'POST'], '/videos', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->getVideos($request, $response, $args);
    });
    $group->get('/playlists', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->getPlaylists($request, $response, $args);
    });
    $group->get('/playlist-videos', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->getPlaylistVideos($request, $response, $args);
    });
    $group->get('/check-login', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->checkLogin($request, $response, $args);
    });
    $group->get('/generate-share-url', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->generateShareUrl($request, $response, $args);
    });
    $group->post('/add-playlist', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->addPlaylist($request, $response, $args);
    });
    $group->post('/add-to-existing-playlist', function (Request $request, Response $response, $args) use ($session, $logManager, $logger) {
        $controller = new App\Controller\VideoController($session, $logManager);
        return $controller->addToExistingPlaylist($request, $response, $args);
    });
}

// APIルートの設定
$app->group('/api', function (RouteCollectorProxy $group) use ($session, $logManager) {
    setApiRoutes($group, $session, $logManager);
});

$app->get('/csrf-token', function (Request $request, Response $response, $args) use ($session, $logger) {
    $csrfToken = bin2hex(random_bytes(32));
    $session->set('csrf_token', $csrfToken);
    $data = ['csrf_token' => $csrfToken];
    $logger->info('CSRF token generated', ['csrf_token' => $csrfToken]);
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/', function (Request $request, Response $response, $args) use ($session, $logger) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $userName = $session->get('user_name');
    $logger->info('Accessed root URL', ['feed_url' => $feedUrl, 'user_name' => $userName]);

    if ($feedUrl) {
        $playlistId = getPlaylistIdFromUrl($feedUrl);
        $videos = getVideosFromPlaylist($playlistId);

        return $view->render($response, 'index.phtml', [
            'auth_url' => '',
            'videos' => $videos,
            'user_name' => $userName
        ]);
    } else {
        return $view->render($response, 'index.phtml');
    }
});

$app->get('/Index', function (Request $request, Response $response, $args) use ($session, $logger) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $userName = $session->get('user_name');
    $logger->info('Accessed /Index URL', ['feed_url' => $feedUrl, 'user_name' => $userName]);

    if ($feedUrl) {
        $playlistId = getPlaylistIdFromUrl($feedUrl);
        $videos = getVideosFromPlaylist($playlistId);

        return $view->render($response, 'index.phtml', [
            'auth_url' => '',
            'videos' => $videos,
            'user_name' => $userName
        ]);
    } else {
        if (!$session->get('token')) {
            return $view->render($response, 'index.phtml', ['auth_url' => '/Index/oauth', 'videos' => [], 'user_name' => $userName]);
        } else {
            return $view->render($response, 'index.phtml', ['auth_url' => '', 'videos' => [], 'user_name' => $userName]);
        }
    }
});

$app->get('/logout', function (Request $request, Response $response, $args) use ($session, $logger) {
    $session->destroy();
    $view = $this->get('view');
    $logger->info('User logged out');
    return $view->render($response, 'logout.phtml');
});

$app->get('/Index/oauth', function (Request $request, Response $response, $args) use ($app, $session, $logger) {
    $client = $app->getContainer()->get('googleClient');
    $logger->info('OAuth process started.');

    if (isset($_GET['logout'])) {
        $session->delete('token');
        $logger->info('User logged out.');
        return $response->withHeader('Location', '/logout')->withStatus(302);
    }

    if (isset($_GET['code'])) {
        try {
            $logger->info('Authorization code received.', ['code' => $_GET['code']]);
            $client->authenticate($_GET['code']);
            $session->set('token', json_encode($client->getAccessToken()));
            $logger->info('Access token obtained.', ['token' => $session->get('token')]);

            $client->setAccessToken(json_decode($session->get('token'), true));
            $oauth2 = new Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            $session->set('user_name', $userInfo->name);
            $logger->info('User name obtained.', ['user_name' => $session->get('user_name')]);

            return $response->withHeader('Location', '/Index')->withStatus(302);
        } catch (Exception $e) {
            $logger->error('Error during OAuth process.', ['exception' => $e]);
            return $response->withStatus(500)->write('An error occurred during the OAuth process.');
        }
    }

    if ($session->get('token')) {
        $client->setAccessToken(json_decode($session->get('token'), true));
        $logger->info('Access token set from session.');
    }

    if (!$client->getAccessToken()) {
        $authUrl = $client->createAuthUrl();
        $logger->info('Redirecting to Google for authentication.', ['auth_url' => $authUrl]);
        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }

    if ($client->getAccessToken()) {
        $session->set('sessionToken', json_encode($client->getAccessToken()));
        $logger->info('User authenticated successfully.');
        return $response->withHeader('Location', '/Index')->withStatus(302);
    } else {
        $logger->error('Failed to obtain access token.');
        return $response->withStatus(500)->write("Can't get access_token");
    }
});

$app->get('/Index/share', function (Request $request, Response $response, $args) use ($logger) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $logger->info('Accessed /Index/share URL', ['feed_url' => $feedUrl]);
    return $view->render($response, 'share.phtml', ['feed_url' => $feedUrl]);
});

// YouTube APIを使用して再生リストの動画を取得する関数
function getPlaylistIdFromUrl($url) {
    parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
    return $queryParams['list'] ?? null;
}

function getVideosFromPlaylist($playlistId) {
    $client = new Google\Client();
    $client->setAuthConfig('/var/www/moreplaylistdev/client_secret.json');
    $client->setDeveloperKey($_SERVER['GOOGLE_DEVELOPER_KEY']);
    $client->setScopes([
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.force-ssl',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ]);

    $youtube = new Google\Service\YouTube($client);

    $videos = [];
    try {
        $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('id,snippet', [
            'playlistId' => $playlistId,
            'maxResults' => 20,
        ]);

        foreach ($playlistItemsResponse->items as $item) {
            $videos[] = [
                'title' => $item->snippet->title,
                'videoId' => $item->snippet->resourceId->videoId,
                'thumbnail' => $item->snippet->thumbnails->medium->url,
            ];
        }
    } catch (\Exception $e) {
        global $logger;
        $logger->error('YouTube API error.', ['exception' => $e]);
    }

    return $videos;
}

