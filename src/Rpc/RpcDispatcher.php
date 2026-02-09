<?php
namespace App\Rpc;

use App\Controller\VideoController;
use App\Utils\LogManager;
use App\Utils\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RpcDispatcher
{
    private SessionManager $session;
    private LogManager $logManager;

    public function __construct(SessionManager $session, LogManager $logManager)
    {
        $this->session = $session;
        $this->logManager = $logManager;
    }

    public function dispatch(Request $request, Response $response): Response
    {
        $payload = json_decode((string) $request->getBody(), true);
        if (!is_array($payload)) {
            return $this->errorResponse($response, null, 40000, 'invalid_request');
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];

        if (($payload['jsonrpc'] ?? null) !== '2.0' || !is_string($method)) {
            return $this->errorResponse($response, $id, 40000, 'invalid_request');
        }

        if (!$this->isAssocArray($params)) {
            return $this->errorResponse($response, $id, 40001, 'invalid_params');
        }

        $controller = new VideoController($this->session, $this->logManager);

        try {
            switch ($method) {
                case 'auth.status':
                    $loggedIn = $controller->isAuthenticated();
                    $result = [
                        'loggedIn' => $loggedIn,
                        'loginUrl' => '/Index/oauth',
                    ];
                    $userName = $this->session->get('user_name');
                    if ($loggedIn && $userName) {
                        $result['userName'] = $userName;
                    }
                    return $this->successResponse($response, $id, $result);
                case 'playlist.list':
                    if (!$controller->isAuthenticated()) {
                        return $this->errorResponse($response, $id, 40100, 'unauthorized', [
                            'loginUrl' => '/Index/oauth',
                        ]);
                    }
                    $playlists = $controller->listPlaylists();
                    return $this->successResponse($response, $id, ['playlists' => $playlists]);
                default:
                    return $this->errorResponse($response, $id, 40004, 'method_not_found');
            }
        } catch (\Throwable $e) {
            $logger = $this->logManager->getLogger();
            $logger->error('RPC handler error', ['exception' => $e]);
            return $this->errorResponse($response, $id, 50000, 'internal_error');
        }
    }

    private function successResponse(Response $response, $id, array $result): Response
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, $id, int $code, string $message, ?array $data = null): Response
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isAssocArray($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if ($value === []) {
            return true;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
