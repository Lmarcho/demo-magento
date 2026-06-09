<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\CustomerPurchaseHistoryServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetCustomerPurchaseHistory;
use PHPUnit\Framework\TestCase;

class GetCustomerPurchaseHistoryTest extends TestCase
{
    public function testSchemaRequiresStoreAndAssertion(): void
    {
        $tool = new GetCustomerPurchaseHistory($this->createMock(CustomerPurchaseHistoryServiceInterface::class));

        self::assertSame(['store_code', 'customer_assertion'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $tool = new GetCustomerPurchaseHistory($this->createMock(CustomerPurchaseHistoryServiceInterface::class));

        try {
            $tool->execute(['store_code' => 'default', 'customer_assertion' => 'assertion', 'limit' => 0]);
            self::fail('Expected invalid limit error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('INVALID_PURCHASE_HISTORY_LIMIT', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredHistory(): void
    {
        $service = $this->createMock(CustomerPurchaseHistoryServiceInterface::class);
        $service->expects(self::once())->method('getHistory')
            ->with('default', 'assertion', ['price'], 2, null, null)
            ->willReturn([
                'history' => ['returned' => 1, 'items' => [['sku' => '24-MB01']]],
                'products' => [['sku' => '24-MB01']],
                'errors' => [],
            ]);

        $result = (new GetCustomerPurchaseHistory($service))->execute([
            'store_code' => 'default',
            'customer_assertion' => 'assertion',
            'sections' => ['price'],
            'limit' => 2,
        ]);

        self::assertSame(1, $result['structuredContent']['history']['returned']);
        self::assertSame('24-MB01', $result['structuredContent']['products'][0]['sku']);
    }
}
