<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\SessionManager;
use Google\Client;
use Google\Service\YouTube;

class VideoController
{
    private $client;
    private $session;

    public function __construct(SessionManager $session)
    {
        error_log('VideoController::__construct called');
        $this->session = $session;
        $this->client = new Client();
        $this->client->setAuthConfig(__DIR__ . '/../../client_secret.json');
        $this->client->setDeveloperKey($_SERVER['GOOGLE_DEVELOPER_KEY']);
        $this->client->setScopes([
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ]);
        $this->client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/Index/oauth');
        $this->client->setAccessType('offline');
    }

    public function getVideos(Request $request, Response $response, $args)
    {
        error_log('VideoController::getVideos called');
        $params = (array)$request->getQueryParams();
        $keyword = $params['keyword'] ?? 'Lo-Fi';

        $token = $this->session->get('token');
        if ($token) {
            $token = json_decode($token, true);
            if (isset($token['access_token'])) {
                error_log('Access token exists in session.');
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    error_log('Access token expired.');
                    $refreshToken = $this->client->getRefreshToken();
                    if (!$refreshToken) {
                        return $response->withHeader('Location', '/logout')->withStatus(302);
                    }
                    $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $this->session->set('token', json_encode($this->client->getAccessToken()));
                    error_log('New access token obtained: ' . print_r($this->session->get('token'), true));
                }
            } else {
                error_log('Access token does not exist in session token.');
            }
        } else {
            error_log('Session token does not exist.');
        }

        $youtube = new YouTube($this->client);

        try {
            $searchResponse = $youtube->search->listSearch('id,snippet', [
                'q' => $keyword,
                'maxResults' => 20,
            ]);

            $data = [];
            foreach ($searchResponse->items as $item) {
                $data[] = [
                    'title' => $item->snippet->title,
                    'videoId' => $item->id->videoId,
                    'thumbnail' => $item->snippet->thumbnails->medium->url,
                ];
            }

            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON encode error: ' . json_last_error_msg());
                $response->getBody()->write(json_encode(['error' => 'Internal server error.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function checkLogin(Request $request, Response $response, $args)
    {
        // セッション内容をエラーログに出力
        error_log('Session contents: ' . print_r($this->session->get('token'), true));

        // ログイン状態のチェック
        $loggedIn = false;
        $token = $this->session->get('token');
        if ($token) {
            $token = json_decode($token, true);
            if (isset($token['access_token'])) {
                $loggedIn = true;
            }
        }

        // ログを追加
        error_log('checkLogin called, loggedIn: ' . ($loggedIn ? 'true' : 'false'));

        // レスポンスの作成
        $response->getBody()->write(json_encode(['loggedIn' => $loggedIn]));

        // レスポンスにヘッダーを追加して返す
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getPlaylists(Request $request, Response $response, $args)
    {
        $token = $this->session->get('token');
        if (!$token) {
            // 認証がない場合のエラーレスポンス
            $response->getBody()->write(json_encode(['error' => 'Unauthorized access. Please log in.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = json_decode($token, true);
        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();
            if (!$refreshToken) {
                return $response->withHeader('Location', '/logout')->withStatus(302);
            }
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $this->session->set('token', json_encode($newToken));
            $this->client->setAccessToken($newToken);
        }

        $youtube = new YouTube($this->client);

        try {
            $playlistsResponse = $youtube->playlists->listPlaylists('id,snippet,status', [
                'mine' => true,
                'maxResults' => 20,
            ]);

            $data = [];
            foreach ($playlistsResponse->items as $item) {
                $data[] = [
                    'title' => $item->snippet->title,
                    'playlistId' => $item->id,
                    'status' => $item->status->privacyStatus,
                ];
            }

            error_log('Playlists fetched: ' . json_encode($data));

            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON encode error: ' . json_last_error_msg());
                $response->getBody()->write(json_encode(['error' => 'Internal server error.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getPlaylistVideos(Request $request, Response $response, $args)
    {
        $playlistId = $request->getQueryParams()['playlistId'];

        $token = $this->session->get('token');
        if ($token) {
            $token = json_decode($token, true);
            if (isset($token['access_token'])) {
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    $refreshToken = $this->client->getRefreshToken();
                    if (!$refreshToken) {
                        return $response->withHeader('Location', '/logout')->withStatus(302);
                    }
                    $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $this->session->set('token', json_encode($this->client->getAccessToken()));
                }
            }
        } else {
            // セッションがない場合はログインしていないのでエラーレスポンスを返す
            return $response->withHeader('Location', '/logout')->withStatus(302);
        }

        $youtube = new YouTube($this->client);

        try {
            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('id,snippet', [
                'playlistId' => $playlistId,
                'maxResults' => 20,
            ]);

            $data = [];
            foreach ($playlistItemsResponse->items as $item) {
                $data[] = [
                    'title' => $item->snippet->title,
                    'videoId' => $item->snippet->resourceId->videoId,
                    'thumbnail' => $item->snippet->thumbnails->medium->url,
                ];
            }

            error_log('Playlist videos fetched: ' . json_encode($data));

            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON encode error: ' . json_last_error_msg());
                $response->getBody()->write(json_encode(['error' => 'Internal server error.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function generateShareUrl(Request $request, Response $response, $args)
    {
        $playlistId = $request->getQueryParams()['playlistId'] ?? null;
        $privacyStatus = $request->getQueryParams()['privacyStatus'] ?? 'public';
        $serverName = $_SERVER['SERVER_NAME'];

        error_log("generateShareUrl called with playlistId: $playlistId");

        if ($playlistId) {
            $longUrl = "https://www.youtube.com/playlist?list=$playlistId";
            error_log("Long URL: $longUrl");
            $shareUrl = "https://$serverName/Index?feed_url=" . urlencode($longUrl);
        } else {
            $shareUrl = '';
        }

        $response->getBody()->write(json_encode(['share_url' => $shareUrl]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function addPlaylist(Request $request, Response $response, $args)
    {
        $params = json_decode($request->getBody()->getContents(), true);
        $videoId = $params['video_id'] ?? null;
        $playlistTitle = $params['playlist_title'] ?? null;
        $privacyStatus = $params['privacyStatus'] ?? null;

        if (!$videoId || !$playlistTitle || !$privacyStatus) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request data.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $token = $this->session->get('token');
        if (!$token) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized access. Please log in.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = json_decode($token, true);
        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();
            if (!$refreshToken) {
                return $response->withHeader('Location', '/logout')->withStatus(302);
            }
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $this->session->set('token', json_encode($newToken));
            $this->client->setAccessToken($newToken);
        }

        $youtube = new YouTube($this->client);

        try {
            // 新しい再生リストを作成
            $playlistSnippet = new YouTube\PlaylistSnippet();
            $playlistSnippet->setTitle($playlistTitle);
            $playlistSnippet->setDescription('A new playlist created from API');
            $playlistStatus = new YouTube\PlaylistStatus();
            $playlistStatus->setPrivacyStatus($privacyStatus);

            $youTubePlaylist = new YouTube\Playlist();
            $youTubePlaylist->setSnippet($playlistSnippet);
            $youTubePlaylist->setStatus($playlistStatus);

            $playlistResponse = $youtube->playlists->insert('snippet,status', $youTubePlaylist);

            $playlistId = $playlistResponse['id'];

            // 動画を再生リストに追加
            $playlistItemSnippet = new YouTube\PlaylistItemSnippet();
            $playlistItemSnippet->setPlaylistId($playlistId);
            $playlistItemSnippet->setResourceId(new YouTube\ResourceId([
                'kind' => 'youtube#video',
                'videoId' => $videoId
            ]));

            $playlistItem = new YouTube\PlaylistItem();
            $playlistItem->setSnippet($playlistItemSnippet);

            $youtube->playlistItems->insert('snippet', $playlistItem);

            $response->getBody()->write(json_encode(['success' => 'Video added to new playlist successfully.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function addToExistingPlaylist(Request $request, Response $response, $args)
    {
        $params = json_decode($request->getBody()->getContents(), true);
        $videoId = $params['video_id'] ?? null;
        $playlistId = $params['playlistId'] ?? null;

        if (!$videoId || !$playlistId) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request data.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $token = $this->session->get('token');
        if (!$token) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized access. Please log in.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = json_decode($token, true);
        $this->client->setAccessToken($token);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();
            if (!$refreshToken) {
                return $response->withHeader('Location', '/logout')->withStatus(302);
            }
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            $this->session->set('token', json_encode($newToken));
            $this->client->setAccessToken($newToken);
        }

        $youtube = new YouTube($this->client);

        try {
            // 動画を既存の再生リストに追加
            $playlistItemSnippet = new YouTube\PlaylistItemSnippet();
            $playlistItemSnippet->setPlaylistId($playlistId);
            $playlistItemSnippet->setResourceId(new YouTube\ResourceId([
                'kind' => 'youtube#video',
                'videoId' => $videoId
            ]));

            $playlistItem = new YouTube\PlaylistItem();
            $playlistItem->setSnippet($playlistItemSnippet);

            $youtube->playlistItems->insert('snippet', $playlistItem);

            $response->getBody()->write(json_encode(['success' => 'Video added to existing playlist successfully.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}

