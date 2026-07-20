<?php

declare(strict_types=1);

use App\Service\YoutubeVideoService;
use App\Utils\LogManager;
use App\Utils\SessionManager;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class YoutubeVideoServiceTest extends TestCase
{
    private function service(): YoutubeVideoService
    {
        return new YoutubeVideoService(new SessionManager(), new LogManager());
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlReturnsListParam(): void
    {
        $url = 'https://www.youtube.com/playlist?list=PL12345&foo=bar';

        $this->assertSame('PL12345', $this->service()->extractPlaylistIdFromFeedUrl($url));
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlReturnsNullWhenMissing(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc123';

        $this->assertNull($this->service()->extractPlaylistIdFromFeedUrl($url));
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlReturnsNullForMalformedUrl(): void
    {
        $url = 'not a url at all';

        $this->assertNull($this->service()->extractPlaylistIdFromFeedUrl($url));
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlIgnoresOtherQueryParams(): void
    {
        $url = 'https://www.youtube.com/playlist?index=3&list=PLabc999&feature=share';

        $this->assertSame('PLabc999', $this->service()->extractPlaylistIdFromFeedUrl($url));
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlReturnsNullForEmptyString(): void
    {
        $this->assertNull($this->service()->extractPlaylistIdFromFeedUrl(''));
    }

    #[RunInSeparateProcess]
    public function testExtractPlaylistIdFromFeedUrlReturnsNullForNull(): void
    {
        $this->assertNull($this->service()->extractPlaylistIdFromFeedUrl(null));
    }
}
