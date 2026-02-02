<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\SessionManager;
use App\Utils\LogManager;
use Google\Client;
use Google\Service\YouTube;

class VideoController
{
    private $client;
    private $session;
    private $logger;

    public function __construct(SessionManager $session, LogManager $logManager)
    {
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
        $this->logger = $logManager->getLogger();
    }

    private function authenticateClient()
    {
        $token = $this->session->get('token');
        if ($token) {
            $token = json_decode($token, true);
            if (isset($token['access_token'])) {
                $this->logger->debug('Access token exists in session.');
                $this->client->setAccessToken($token);

                if ($this->client->isAccessTokenExpired()) {
                    $this->logger->debug('Access token expired.');
                    $refreshToken = $this->client->getRefreshToken();
                    if (!$refreshToken) {
                        return false;
                    }
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $this->session->set('token', json_encode($newToken));
                    $this->logger->debug('New access token obtained.', ['token' => $newToken]);
                }
                return true;
            } else {
                $this->logger->warning('Access token does not exist in session token.');
            }
        } else {
            $this->logger->warning('Session token does not exist.');
        }
        return false;
    }

public function getVideos(Request $request, Response $response, $args)
{
    $this->logger->info('VideoController::getVideos called');

    // クエリパラメータを取得
    $queryParams = $request->getQueryParams();
    $this->logger->info('queryParams value', ['queryParams' => $queryParams]);

    // feed_url を取得し、URLデコードする
    $feedUrl = isset($queryParams['feed_url']) ? urldecode($queryParams['feed_url']) : null;
    $this->logger->info('feed_url value', ['feed_url' => $feedUrl]);

    $youtube = new YouTube($this->client);

    try {
        if ($feedUrl) {
            $this->logger->info('Feed URL detected', ['feed_url' => $feedUrl]);
            $playlistId = $this->extractPlaylistId($feedUrl);
            if ($playlistId) {
                $this->logger->info('Extracted playlist ID', ['playlistId' => $playlistId]);
                $playlistItems = $youtube->playlistItems->listPlaylistItems('snippet', ['playlistId' => $playlistId]);

                $videos = [];
                foreach ($playlistItems['items'] as $item) {
                    $thumbnails = $item['snippet']['thumbnails']['default'] ?? null;
                    $videos[] = [
                        'title' => $item['snippet']['title'],
                        'videoId' => $item['snippet']['resourceId']['videoId'] ?? null,
                        'thumbnail' => $thumbnails ? $thumbnails['url'] : null,
                    ];
                }

                $this->logger->info('Videos retrieved successfully from playlist', ['videos' => $videos]);
                $response->getBody()->write(json_encode(['videos' => $videos]));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $this->logger->error('Failed to extract playlist ID from feed URL');
            }
        }

        $this->logger->info('No valid feed URL provided, performing search');
        $searchQuery = $queryParams['keyword'] ?? 'Lo-Fi';

        $searchResponse = $youtube->search->listSearch('snippet', [
            'q' => $searchQuery,
            'maxResults' => 10,
        ]);

        $videos = [];
        foreach ($searchResponse['items'] as $searchResult) {
            $thumbnails = $searchResult['snippet']['thumbnails']['default'] ?? null;
            $videos[] = [
                'title' => $searchResult['snippet']['title'],
                'videoId' => $searchResult['id']['videoId'] ?? null,
                'thumbnail' => $thumbnails ? $thumbnails['url'] : null,
            ];
        }

        $this->logger->info('Search results retrieved successfully', ['videos' => $videos]);
        $response->getBody()->write(json_encode(['videos' => $videos]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $this->logger->error('YouTube API error: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}

private function extractPlaylistId($feedUrl)
{
    $this->logger->info('Extracting playlist ID from feed URL', ['feed_url' => $feedUrl]);

    parse_str(parse_url($feedUrl, PHP_URL_QUERY), $queryParams);
    $playlistId = $queryParams['list'] ?? null;

    $this->logger->info('Extracted playlist ID from feed URL', ['playlistId' => $playlistId, 'feed_url' => $feedUrl, 'queryParams' => $queryParams]);
    return $playlistId;
}



    public static function getVideosByKeyword($keyword)
    {
        $client = new Client();
        $client->setAuthConfig('/var/www/moreplaylistdev/client_secret.json');
        $client->setDeveloperKey($_SERVER['GOOGLE_DEVELOPER_KEY']);
        $client->setScopes([
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ]);

        $youtube = new YouTube($client);

        try {
            $searchParams = [
                'q' => $keyword,
                'maxResults' => 20,
            ];

            $searchResponse = $youtube->search->listSearch('id,snippet', $searchParams);

            $videos = [];
            foreach ($searchResponse->items as $item) {
                $videos[] = [
                    'title' => $item->snippet->title,
                    'videoId' => $item->id->videoId,
                    'thumbnail' => $item->snippet->thumbnails->medium->url,
                ];
            }

            return $videos;
        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            return [];
        }
    }

    public function checkLogin(Request $request, Response $response, $args)
    {
        $this->logger->debug('Session contents: ' . print_r($this->session->get('token'), true));
        $loggedIn = $this->authenticateClient();
        $this->logger->info('checkLogin called, loggedIn: ' . ($loggedIn ? 'true' : 'false'));
        $response->getBody()->write(json_encode(['loggedIn' => $loggedIn]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getPlaylists(Request $request, Response $response, $args)
    {
        if (!$this->authenticateClient()) {
            return $response->withHeader('Location', '/logout')->withStatus(302);
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

            $this->logger->info('Playlists fetched: ' . json_encode($data));

            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON encode error: ' . json_last_error_msg());
                $response->getBody()->write(json_encode(['error' => 'Internal server error.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getPlaylistVideos(Request $request, Response $response, $args)
    {
        if (!$this->authenticateClient()) {
            return $response->withHeader('Location', '/logout')->withStatus(302);
        }

        $playlistId = $request->getQueryParams()['playlistId'];
        $youtube = new YouTube($this->client);

        try {
            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('id,snippet', [
                'playlistId' => $playlistId,
                'maxResults' => 20,
            ]);

            $data = [];
            foreach ($playlistItemsResponse->items as $item) {
                $title = $item->snippet->title;
                $videoId = $item->snippet->resourceId->videoId;
                $thumbnail = isset($item->snippet->thumbnails->medium->url) ? $item->snippet->thumbnails->medium->url : null;

                if ($title === 'Deleted video' || $videoId === 'Deleted video' || $thumbnail === null) {
                    continue;
                }

                $data[] = [
                    'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                    'videoId' => htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8'),
                    'thumbnail' => htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'),
                ];
            }

            $this->logger->info('Playlist videos fetched: ' . json_encode($data));

            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON encode error: ' . json_last_error_msg());
                $response->getBody()->write(json_encode(['error' => 'Internal server error.']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public static function getPlaylistIdFromUrl($url)
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
        return $queryParams['list'] ?? null;
    }

    public static function getVideosFromPlaylist($playlistId, $pageToken = null)
    {
        $client = new Client();
        $client->setAuthConfig('/var/www/moreplaylistdev/client_secret.json');
        $client->setDeveloperKey($_SERVER['GOOGLE_DEVELOPER_KEY']);
        $client->setScopes([
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ]);

        $youtube = new YouTube($client);

        $videos = [];
        try {
            $params = [
                'playlistId' => $playlistId,
                'maxResults' => 20,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('id,snippet', $params);

            foreach ($playlistItemsResponse->items as $item) {
                $thumbnails = $item['snippet']['thumbnails']['default'] ?? null;
                $videos[] = [
                    'title' => $item['snippet']['title'],
                    'videoId' => $item['snippet']['resourceId']['videoId'] ?? null,
                    'thumbnail' => $thumbnails ? $thumbnails['url'] : null,
                ];
            }

            $nextPageToken = $playlistItemsResponse->nextPageToken ?? null;
            $prevPageToken = $playlistItemsResponse->prevPageToken ?? null;

            return ['videos' => $videos, 'nextPageToken' => $nextPageToken, 'prevPageToken' => $prevPageToken];

        } catch (\Exception $e) {
            error_log('YouTube API error: ' . $e->getMessage());
            return ['videos' => $videos, 'nextPageToken' => null, 'prevPageToken' => null];
        }
    }

    public function generateShareUrl(Request $request, Response $response, $args)
    {
        $playlistId = $request->getQueryParams()['playlistId'] ?? null;
        $privacyStatus = $request->getQueryParams()['privacyStatus'] ?? 'public';
        $serverName = $_SERVER['SERVER_NAME'];

        $this->logger->info("generateShareUrl called with playlistId: $playlistId");

        if ($playlistId) {
            $longUrl = "https://www.youtube.com/playlist?list=$playlistId";
            $this->logger->debug("Long URL: $longUrl");
            $shareUrl = "https://$serverName/Index?feed_url=" . urlencode($longUrl);
        } else {
            $shareUrl = '';
        }

        $response->getBody()->write(json_encode(['share_url' => $shareUrl]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function addPlaylist(Request $request, Response $response, $args) {
        $params = json_decode($request->getBody()->getContents(), true);
        $playlistName = $params['new_playlist_title'] ?? null;
        $playlistPrivacy = $params['new_playlist_privacy'] ?? null;
        $videoId = $params['video_id'] ?? null;

         // バリデーション：名前、プライバシー設定、videoIdが存在することを確認
        if (!$playlistName || !$playlistPrivacy || !$videoId) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request data.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!$this->authenticateClient()) {
            return $response->withHeader('Location', '/logout')->withStatus(302);
        }

        $youtube = new YouTube($this->client);

        try {
            // プレイリストの作成
            $playlistSnippet = new YouTube\PlaylistSnippet();
            $playlistSnippet->setTitle($playlistName);
            $playlistSnippet->setDescription('A new playlist created from API');
            $playlistStatus = new YouTube\PlaylistStatus();
            $playlistStatus->setPrivacyStatus($playlistPrivacy);

             $youTubePlaylist = new YouTube\Playlist();
             $youTubePlaylist->setSnippet($playlistSnippet);
             $youTubePlaylist->setStatus($playlistStatus);

             $playlistResponse = $youtube->playlists->insert('snippet,status', $youTubePlaylist);

             $playlistId = $playlistResponse['id'];

             // 新規作成したプレイリストに動画を追加
             $playlistItemSnippet = new YouTube\PlaylistItemSnippet();
             $playlistItemSnippet->setPlaylistId($playlistId);
             $playlistItemSnippet->setResourceId(new YouTube\ResourceId([
                'kind' => 'youtube#video',
                'videoId' => $videoId
            ]));

            $playlistItem = new YouTube\PlaylistItem();
            $playlistItem->setSnippet($playlistItemSnippet);

            $youtube->playlistItems->insert('snippet', $playlistItem);

            $response->getBody()->write(json_encode(['success' => 'Playlist created and video added successfully.', 'playlistId' => $playlistId]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('YouTube API error: ' . $e->getMessage());
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

        if (!$this->authenticateClient()) {
            return $response->withHeader('Location', '/logout')->withStatus(302);
        }

        $youtube = new YouTube($this->client);

        try {
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
            $this->logger->error('YouTube API error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}

