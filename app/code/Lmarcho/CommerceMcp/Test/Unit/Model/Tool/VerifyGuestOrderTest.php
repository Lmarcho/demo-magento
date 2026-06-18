<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\OrderStatusServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\VerifyGuestOrder;
use PHPUnit\Framework\TestCase;

class VerifyGuestOrderTest extends TestCase
{
    public function testSchemaRequiresStoreOrderAndContact(): void
    {
        $tool = new VerifyGuestOrder(
            $this->createMock(OrderStatusServiceInterface::class)
        );

        self::assertSame(
            ['store_code', 'order_number', 'contact'],
            $tool->getInputSchema()['required']
        );
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsUnknownArguments(): void
    {
        $tool = new VerifyGuestOrder(
            $this->createMock(OrderStatusServiceInterface::class)
        );

        try {
            $tool->execute([
                'store_code' => 'default',
                'order_number' => '000000001',
                'contact' => 'customer@example.test',
                'customer_id' => 123,
            ]);
            self::fail('Expected unknown argument error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('UNKNOWN_ARGUMENT', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredGuestOrderStatus(): void
    {
        $service = $this->createMock(OrderStatusServiceInterface::class);
        $service->expects(self::once())->method('verifyGuest')
            ->with('default', '000000001', 'customer@example.test')
            ->willReturn([
                'order' => [
                    'order_number' => '000000001',
                    'status' => 'processing',
                    'status_label' => 'Processing',
                    'items_count' => 1,
                ],
            ]);

        $result = (new VerifyGuestOrder($service))->execute([
            'store_code' => 'default',
            'order_number' => '000000001',
            'contact' => 'customer@example.test',
        ]);

        self::assertSame('000000001', $result['structuredContent']['order']['order_number']);
        self::assertFalse($result['isError']);
    }
}
