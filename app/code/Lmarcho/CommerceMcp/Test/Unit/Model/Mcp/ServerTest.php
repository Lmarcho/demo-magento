<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Mcp;

use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Mcp\JsonRpcParser;
use Lmarcho\CommerceMcp\Model\Mcp\ProtocolNegotiator;
use Lmarcho\CommerceMcp\Model\Mcp\ResponseBuilder;
use Lmarcho\CommerceMcp\Model\Mcp\Server;
use Lmarcho\CommerceMcp\Model\Mcp\ToolRegistry;
use Lmarcho\CommerceMcp\Service\RateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ServerTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server(
            new JsonRpcParser(),
            new ProtocolNegotiator(),
            new ResponseBuilder(),
            new ToolRegistry(),
            $this->createMock(Config::class),
            $this->createMock(RateLimiter::class),
            new NullLogger()
        );
    }

    public function testInitializeReturnsNegotiatedVersion(): void
    {
        $response = $this->server->handle(
            '{"jsonrpc":"2.0","id":1,"method":"initialize","params":'
            . '{"protocolVersion":"2025-11-25","capabilities":{},'
            . '"clientInfo":{"name":"test","version":"1"}}}',
            'correlation-123',
            []
        );

        self::assertSame('2025-11-25', $response['result']['protocolVersion']);
        self::assertSame('correlation-123', $response['result']['_meta']['correlation_id']);
    }

    public function testToolsListHonorsAcl(): void
    {
        $response = $this->server->handle(
            '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}',
            'correlation-123',
            ['get_store_context']
        );

        self::assertCount(1, $response['result']['tools']);
        self::assertSame('get_store_context', $response['result']['tools'][0]['name']);
    }

    public function testForbiddenToolReturnsAccessDenied(): void
    {
        $response = $this->server->handle(
            '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":'
            . '{"name":"get_order_status","arguments":{}}}',
            'correlation-123',
            ['get_store_context']
        );

        self::assertSame(-32003, $response['error']['code']);
    }

    public function testAllowedButUnimplementedToolFailsClosed(): void
    {
        $response = $this->server->handle(
            '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":'
            . '{"name":"get_store_context","arguments":{}}}',
            'correlation-123',
            ['get_store_context']
        );

        self::assertSame(-32004, $response['error']['code']);
    }

    public function testInitializedNotificationHasNoResponse(): void
    {
        self::assertNull($this->server->handle(
            '{"jsonrpc":"2.0","method":"notifications/initialized","params":{}}',
            'correlation-123',
            []
        ));
    }
}
