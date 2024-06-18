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

        if ($feedUrl) {
            $playlistId = VideoController::getPlaylistIdFromUrl($feedUrl);
            $videos = VideoController::getVideosFromPlaylist($playlistId);

            return $this->view->render($response, 'index.phtml', [
                'auth_url' => '',
                'videos' => $videos,
                'user_name' => $userName
            ]);
        } else {
            if (!$this->session->get('token')) {
                return $this->view->render($response, 'index.phtml', ['auth_url' => '/Index/oauth', 'videos' => [], 'user_name' => $userName]);
            } else {
                return $this->view->render($response, 'index.phtml', ['auth_url' => '', 'videos' => [], 'user_name' => $userName]);
            }
        }
    }
}

