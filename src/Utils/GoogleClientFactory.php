<?php
namespace App\Utils;

use Google\Client;

class GoogleClientFactory
{
    private const PRODUCTION_REDIRECT_URI = 'https://moreplaylist.appstarrocks.com/Index/oauth';

    private string $clientSecretPath;

    public function __construct(?string $clientSecretPath = null)
    {
        $this->clientSecretPath = $clientSecretPath ?? (__DIR__ . '/../../client_secret.json');
    }

    public function create(?string $developerKey = null): Client
    {
        $client = new Client();
        $client->setAuthConfig($this->clientSecretPath);
        $client->setRedirectUri($this->getRedirectUri());
        $client->setScopes([
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/youtube.force-ssl',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ]);
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setIncludeGrantedScopes(true);

        if ($developerKey) {
            $client->setDeveloperKey($developerKey);
        }

        return $client;
    }

    private function getRedirectUri(): string
    {
        $configuredUri = $_SERVER['GOOGLE_OAUTH_REDIRECT_URI'] ?? getenv('GOOGLE_OAUTH_REDIRECT_URI');
        if (is_string($configuredUri) && $configuredUri !== '') {
            return $configuredUri;
        }

        $environment = strtolower((string) ($_SERVER['APPLICATION_ENV'] ?? 'production'));
        if (in_array($environment, ['local', 'development'], true)) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            return 'https://' . $host . '/Index/oauth';
        }

        return self::PRODUCTION_REDIRECT_URI;
    }
}
