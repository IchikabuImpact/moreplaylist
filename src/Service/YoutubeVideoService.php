<?php
namespace App\Service;

use App\Utils\LogManager;
use App\Utils\SessionManager;

class YoutubeVideoService
{
    private $session;
    private $logger;

    public function __construct(SessionManager $session, LogManager $logManager)
    {
        $this->session = $session;
        $this->logger = $logManager->getLogger();
    }

    public function extractPlaylistIdFromFeedUrl(?string $feedUrl): ?string
    {
        $this->logger->info('Extracting playlist ID from feed URL', ['feed_url' => $feedUrl]);

        parse_str(parse_url((string) $feedUrl, PHP_URL_QUERY) ?? '', $queryParams);
        $playlistId = $queryParams['list'] ?? null;

        $this->logger->info('Extracted playlist ID from feed URL', [
            'playlistId' => $playlistId,
            'feed_url' => $feedUrl,
            'queryParams' => $queryParams,
        ]);

        return $playlistId;
    }
}
