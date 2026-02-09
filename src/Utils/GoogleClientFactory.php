<?php
namespace App\Utils;

use Google\Client;

class GoogleClientFactory
{
    private string $clientSecretPath;

    public function __construct(?string $clientSecretPath = null)
    {
        $this->clientSecretPath = $clientSecretPath ?? (__DIR__ . '/../../client_secret.json');
    }

    public function create(?string $developerKey = null): Client
    {
        $client = new Client();
        $client->setAuthConfig($this->clientSecretPath);
        $client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/Index/oauth');
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
}
