<?php

declare(strict_types=1);

use App\Utils\GoogleClientFactory;
use PHPUnit\Framework\TestCase;

final class GoogleClientFactoryTest extends TestCase
{
    private string $clientSecretPath;

    protected function setUp(): void
    {
        $this->clientSecretPath = tempnam(sys_get_temp_dir(), 'google-client-');
        file_put_contents($this->clientSecretPath, json_encode([
            'web' => [
                'client_id' => 'test.apps.googleusercontent.com',
                'client_secret' => 'test-secret',
                'redirect_uris' => ['https://example.com/Index/oauth'],
            ],
        ]));

        unset($_SERVER['GOOGLE_OAUTH_REDIRECT_URI']);
        putenv('GOOGLE_OAUTH_REDIRECT_URI');
    }

    protected function tearDown(): void
    {
        @unlink($this->clientSecretPath);
        unset(
            $_SERVER['APPLICATION_ENV'],
            $_SERVER['HTTP_HOST'],
            $_SERVER['GOOGLE_OAUTH_REDIRECT_URI']
        );
        putenv('GOOGLE_OAUTH_REDIRECT_URI');
    }

    public function testProductionRedirectDoesNotDependOnRequestHost(): void
    {
        $_SERVER['APPLICATION_ENV'] = 'production';
        $_SERVER['HTTP_HOST'] = 'internal-proxy.example:8080';

        $client = (new GoogleClientFactory($this->clientSecretPath))->create();

        $this->assertSame(
            'https://moreplaylist.appstarrocks.com/Index/oauth',
            $client->getRedirectUri()
        );
    }

    public function testConfiguredRedirectUriTakesPriority(): void
    {
        $_SERVER['GOOGLE_OAUTH_REDIRECT_URI'] = 'https://staging.example.com/Index/oauth';

        $client = (new GoogleClientFactory($this->clientSecretPath))->create();

        $this->assertSame('https://staging.example.com/Index/oauth', $client->getRedirectUri());
    }

    public function testLocalRedirectUsesRequestHost(): void
    {
        $_SERVER['APPLICATION_ENV'] = 'local';
        $_SERVER['HTTP_HOST'] = 'localhost:8443';

        $client = (new GoogleClientFactory($this->clientSecretPath))->create();

        $this->assertSame('https://localhost:8443/Index/oauth', $client->getRedirectUri());
    }
}
