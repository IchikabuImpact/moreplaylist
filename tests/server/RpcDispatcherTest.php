<?php

declare(strict_types=1);

use App\Rpc\RpcDispatcher;
use App\Utils\LogManager;
use App\Utils\SessionManager;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class RpcDispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        // VideoController's constructor builds a GoogleClientFactory client, which
        // reads $_SERVER['HTTP_HOST'] unconditionally (always set on a real
        // request; unset under the CLI SAPI). Set it to mirror real request
        // conditions rather than touching production code for a test-only gap.
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    private function dispatch(array $body): array
    {
        $session = new SessionManager();
        $dispatcher = new RpcDispatcher($session, new LogManager());

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/rpc');
        $request = $request->withBody((new StreamFactory())->createStream(json_encode($body)));
        $response = (new ResponseFactory())->createResponse();

        $result = $dispatcher->dispatch($request, $response);

        return [
            'status' => $result->getStatusCode(),
            'json' => json_decode((string) $result->getBody(), true),
        ];
    }

    private function dispatchRaw(string $rawBody): array
    {
        $session = new SessionManager();
        $dispatcher = new RpcDispatcher($session, new LogManager());

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/rpc');
        $request = $request->withBody((new StreamFactory())->createStream($rawBody));
        $response = (new ResponseFactory())->createResponse();

        $result = $dispatcher->dispatch($request, $response);

        return [
            'status' => $result->getStatusCode(),
            'json' => json_decode((string) $result->getBody(), true),
        ];
    }

    #[RunInSeparateProcess]
    public function testMalformedJsonReturnsInvalidRequest(): void
    {
        $result = $this->dispatchRaw('not json at all');

        $this->assertSame(200, $result['status']);
        $this->assertSame(40000, $result['json']['error']['code']);
        $this->assertSame('invalid_request', $result['json']['error']['message']);
    }

    #[RunInSeparateProcess]
    public function testWrongJsonrpcVersionReturnsInvalidRequest(): void
    {
        $result = $this->dispatch(['jsonrpc' => '1.0', 'id' => 1, 'method' => 'auth.status', 'params' => []]);

        $this->assertSame(40000, $result['json']['error']['code']);
    }

    #[RunInSeparateProcess]
    public function testNonStringMethodReturnsInvalidRequest(): void
    {
        $result = $this->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 123, 'params' => []]);

        $this->assertSame(40000, $result['json']['error']['code']);
    }

    #[RunInSeparateProcess]
    public function testNonAssocParamsReturnsInvalidParams(): void
    {
        $result = $this->dispatch([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'auth.status',
            'params' => ['a', 'b', 'c'],
        ]);

        $this->assertSame(40001, $result['json']['error']['code']);
        $this->assertSame('invalid_params', $result['json']['error']['message']);
    }

    #[RunInSeparateProcess]
    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $result = $this->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'no.such.method', 'params' => []]);

        $this->assertSame(40004, $result['json']['error']['code']);
        $this->assertSame('method_not_found', $result['json']['error']['message']);
    }

    #[RunInSeparateProcess]
    public function testAuthStatusWhenLoggedOut(): void
    {
        $result = $this->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'auth.status', 'params' => []]);

        $this->assertSame(200, $result['status']);
        $this->assertFalse($result['json']['result']['loggedIn']);
        $this->assertSame('/Index/oauth', $result['json']['result']['loginUrl']);
        $this->assertArrayNotHasKey('userName', $result['json']['result']);
    }

    #[RunInSeparateProcess]
    public function testPlaylistListWhenLoggedOut(): void
    {
        $result = $this->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'playlist.list', 'params' => []]);

        $this->assertSame(40100, $result['json']['error']['code']);
        $this->assertSame('/Index/oauth', $result['json']['error']['data']['loginUrl']);
    }
}
