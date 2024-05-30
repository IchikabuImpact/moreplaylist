<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Google\Client;
use Google\Service\Oauth2;

$app->group('/api', function (RouteCollectorProxy $group) {
    error_log('Adding routes to group');
    $group->map(['GET', 'POST'], '/videos', 'App\Controller\VideoController:getVideos');
    $group->get('/playlists', 'App\Controller\VideoController:getPlaylists');
    $group->get('/playlist-videos', 'App\Controller\VideoController:getPlaylistVideos');
    $group->get('/check-login', 'App\Controller\VideoController:checkLogin');
    $group->get('/generate-share-url', 'App\Controller\VideoController:generateShareUrl'); // 共有URL生成のルートを追加
    $group->post('/add-playlist', 'App\Controller\VideoController:addPlaylist');
    $group->post('/add-to-existing-playlist', 'App\Controller\VideoController:addToExistingPlaylist');
});

$app->get('/csrf-token', function (Request $request, Response $response, $args) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
    $data = ['csrf_token' => $csrfToken];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/', function (Request $request, Response $response, $args) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;

    if ($feedUrl) {
        // 再生リストの動画を取得する処理を追加
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

$app->get('/Index', function (Request $request, Response $response, $args) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;

    if ($feedUrl) {
        // 再生リストの動画を取得する処理を追加
        $playlistId = getPlaylistIdFromUrl($feedUrl);
        $videos = getVideosFromPlaylist($playlistId);

        return $view->render($response, 'index.phtml', [
            'auth_url' => '',
            'videos' => $videos,
            'user_name' => $userName
        ]);
    } else {
        if (!isset($_SESSION['token'])) {
            return $view->render($response, 'index.phtml', ['auth_url' => '/Index/oauth', 'videos' => [], 'user_name' => $userName]);
        } else {
            // 動画一覧表示の処理はここで行う
            return $view->render($response, 'index.phtml', ['auth_url' => '', 'videos' => [], 'user_name' => $userName]);
        }
    }
});

$app->get('/logout', function (Request $request, Response $response, $args) {
    session_destroy();
    $view = $this->get('view');
    return $view->render($response, 'logout.phtml');
});

$app->get('/Index/oauth', function (Request $request, Response $response, $args) use ($app) {
    $client = $app->getContainer()->get('googleClient');
    error_log('OAuth process started.');

    if (isset($_GET['logout'])) {
        unset($_SESSION['token']);
        error_log('User logged out.');
        return $response->withHeader('Location', '/logout')->withStatus(302);
    }

    if (isset($_GET['code'])) {
        try {
            error_log('Authorization code received: ' . $_GET['code']);
            $client->authenticate($_GET['code']);
            $_SESSION['token'] = json_encode($client->getAccessToken());
            error_log('Access token obtained: ' . json_encode($_SESSION['token']));

            // ユーザー情報を取得する
            $client->setAccessToken(json_decode($_SESSION['token'], true));
            $oauth2 = new Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            $_SESSION['user_name'] = $userInfo->name;
            error_log('User name obtained: ' . $_SESSION['user_name']);

            return $response->withHeader('Location', '/Index')->withStatus(302);
        } catch (Exception $e) {
            error_log('Error during OAuth process: ' . $e->getMessage());
            return $response->withStatus(500)->write('An error occurred during the OAuth process.');
        }
    }

    if (isset($_SESSION['token'])) {
        $client->setAccessToken(json_decode($_SESSION['token'], true));
        error_log('Access token set from session.');
    }

    if (!$client->getAccessToken()) {
        $authUrl = $client->createAuthUrl();
        error_log('Redirecting to Google for authentication: ' . $authUrl);
        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }

    if ($client->getAccessToken()) {
        $_SESSION['sessionToken'] = json_encode($client->getAccessToken());
        $google_token = json_decode($_SESSION['sessionToken'], true);
        error_log('User authenticated successfully.');
        return $response->withHeader('Location', '/Index')->withStatus(302);
    } else {
        error_log('Failed to obtain access token.');
        return $response->withStatus(500)->write("Can't get access_token");
    }
});

$app->get('/Index/share', function (Request $request, Response $response, $args) {
    $view = $this->get('view');
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    return $view->render($response, 'share.phtml', ['feed_url' => $feedUrl]);
});

function getPlaylistIdFromUrl($url)
{
    parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
    return $queryParams['list'] ?? null;
}

function getVideosFromPlaylist($playlistId)
{
    // YouTube APIを使って再生リストの動画を取得する処理を実装します
    $client = new Google\Client();
    $client->setAuthConfig('/var/www/moreplaylistdev/client_secret.json'); // 適切なパスに変更
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
        error_log('YouTube API error: ' . $e->getMessage());
    }

    return $videos;
}

