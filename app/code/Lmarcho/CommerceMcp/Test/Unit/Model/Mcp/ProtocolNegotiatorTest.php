<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Mcp\ProtocolNegotiator;
use PHPUnit\Framework\TestCase;

class ProtocolNegotiatorTest extends TestCase
{
    public function testAcceptsPrimaryVersion(): void
    {
        $negotiator = new ProtocolNegotiator();

        self::assertSame(
            '2025-11-25',
            $negotiator->negotiate('2025-11-25', 1)
        );
    }

    public function testRejectsUnsupportedVersionWithSupportedList(): void
    {
        try {
            (new ProtocolNegotiator())->negotiate('2025-03-26', 9);
            self::fail('Expected unsupported protocol exception.');
        } catch (JsonRpcException $exception) {
            self::assertSame(-32602, $exception->getRpcCode());
            self::assertSame(9, $exception->getRequestId());
            self::assertSame(['supported' => ['2025-11-25']], $exception->getErrorData());
        }
    }
}
