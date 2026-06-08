<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Mcp\JsonRpcParser;
use PHPUnit\Framework\TestCase;

class JsonRpcParserTest extends TestCase
{
    private JsonRpcParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonRpcParser();
    }

    public function testParsesNamedParameterRequest(): void
    {
        $request = $this->parser->parse(
            '{"jsonrpc":"2.0","id":7,"method":"ping","params":{}}'
        );

        self::assertSame(7, $request['id']);
        self::assertSame('ping', $request['method']);
        self::assertFalse($request['notification']);
    }

    public function testRecognizesNotification(): void
    {
        $request = $this->parser->parse(
            '{"jsonrpc":"2.0","method":"notifications/initialized"}'
        );

        self::assertTrue($request['notification']);
        self::assertNull($request['id']);
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionMessage('Parse error');

        $this->parser->parse('{');
    }

    public function testRejectsMissingJsonRpcVersion(): void
    {
        try {
            $this->parser->parse('{"id":"abc","method":"ping"}');
            self::fail('Expected invalid request exception.');
        } catch (JsonRpcException $exception) {
            self::assertSame(-32600, $exception->getRpcCode());
            self::assertSame('abc', $exception->getRequestId());
        }
    }
}
