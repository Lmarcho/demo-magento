<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\OrderStatusServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetOrderStatus;
use PHPUnit\Framework\TestCase;

class GetOrderStatusTest extends TestCase
{
    public function testSchemaRequiresStoreOrderAndAssertion(): void
    {
        $tool = new GetOrderStatus(
            $this->createMock(OrderStatusServiceInterface::class)
        );

        self::assertSame(
            ['store_code', 'order_number', 'customer_assertion'],
            $tool->getInputSchema()['required']
        );
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsUnknownArguments(): void
    {
        $tool = new GetOrderStatus(
            $this->createMock(OrderStatusServiceInterface::class)
        );

        try {
            $tool->execute([
                'store_code' => 'default',
                'order_number' => '000000001',
                'customer_assertion' => 'assertion',
                'email' => 'customer@example.test',
            ]);
            self::fail('Expected unknown argument error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('UNKNOWN_ARGUMENT', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredOrderStatus(): void
    {
        $service = $this->createMock(OrderStatusServiceInterface::class);
        $service->expects(self::once())->method('get')
            ->with('default', '000000001', 'assertion')
            ->willReturn([
                'order' => [
                    'order_number' => '000000001',
                    'status' => 'processing',
                    'status_label' => 'Processing',
                    'items' => [],
                    'shipments' => [],
                ],
            ]);

        $result = (new GetOrderStatus($service))->execute([
            'store_code' => 'default',
            'order_number' => '000000001',
            'customer_assertion' => 'assertion',
        ]);

        self::assertSame('000000001', $result['structuredContent']['order']['order_number']);
        self::assertFalse($result['isError']);
    }
}
