<?php

declare(strict_types=1);

use App\Controller\VideoController;
use PHPUnit\Framework\TestCase;

final class VideoControllerTest extends TestCase
{
    public function testGetPlaylistIdFromUrlReturnsListParam(): void
    {
        $url = 'https://www.youtube.com/playlist?list=PL12345&foo=bar';

        $this->assertSame('PL12345', VideoController::getPlaylistIdFromUrl($url));
    }

    public function testGetPlaylistIdFromUrlReturnsNullWhenMissing(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc123';

        $this->assertNull(VideoController::getPlaylistIdFromUrl($url));
    }
}
