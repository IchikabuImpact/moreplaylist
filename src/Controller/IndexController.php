<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\SessionManager;
use App\Utils\LogManager;
use Slim\Views\PhpRenderer;

class IndexController
{
    private $session;
    private $logger;
    private $view;

    public function __construct(SessionManager $session, LogManager $logManager, PhpRenderer $view)
    {
        $this->session = $session;
        $this->logger = $logManager->getLogger();
        $this->view = $view;
    }


public function handleRootAndIndex(Request $request, Response $response)
{
    $feedUrl = $request->getQueryParams()['feed_url'] ?? null;
    $userName = $this->session->get('user_name');
    $this->logger->info('Accessed URL', ['feed_url' => $feedUrl, 'user_name' => $userName]);

    // feed_url を使って検索
    if ($feedUrl) {
        $this->logger->info('Calling getPlaylistIdFromUrl', ['feed_url' => $feedUrl]);
        $playlistId = VideoController::getPlaylistIdFromUrl($feedUrl);
        $this->logger->info('Playlist ID obtained', ['playlistId' => $playlistId]);

        if ($playlistId) {
            $this->logger->info('Calling getVideosFromPlaylist', ['playlistId' => $playlistId]);
            $videos = VideoController::getVideosFromPlaylist($playlistId);
            $this->logger->info('Videos obtained from playlist', ['videos' => $videos]);

            $this->logger->info('Rendering response with playlist videos');
            return $this->view->render($response, 'index.phtml', [
                'auth_url' => '',
                'videos' => $videos,
                'user_name' => $userName
            ]);
        } else {
            $this->logger->warning('Failed to get playlist ID from feed URL');
            // エラー処理を追加
            $this->logger->info('Rendering response with error');
            return $this->view->render($response, 'index.phtml', [
                'auth_url' => '',
                'videos' => [],
                'user_name' => $userName,
                'error' => 'Invalid feed URL'
            ]);
        }
    } else {
        // キーワードで検索
        $params = $request->getQueryParams();
        $keyword = $params['keyword'] ?? 'Lo-Fi';

        $this->logger->info('Calling getVideosByKeyword', ['keyword' => $keyword]);
        $videos = VideoController::getVideosByKeyword($keyword);
        $this->logger->info('Videos obtained by keyword', ['videos' => $videos]);

        $authUrl = $this->session->get('token') ? '' : '/Index/oauth';
        $this->logger->info('Rendering response with keyword videos');
        return $this->view->render($response, 'index.phtml', [
            'auth_url' => $authUrl,
            'videos' => $videos,
            'user_name' => $userName
        ]);
    }
}




}

