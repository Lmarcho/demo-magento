<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\CustomerCartServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetCustomerCart;
use PHPUnit\Framework\TestCase;

class GetCustomerCartTest extends TestCase
{
    public function testSchemaRequiresStoreAndAssertion(): void
    {
        $tool = new GetCustomerCart($this->createMock(CustomerCartServiceInterface::class));

        self::assertSame(['store_code', 'customer_assertion'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsMissingAssertion(): void
    {
        $tool = new GetCustomerCart($this->createMock(CustomerCartServiceInterface::class));

        try {
            $tool->execute(['store_code' => 'default']);
            self::fail('Expected missing assertion error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('CUSTOMER_ASSERTION_REQUIRED', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredCart(): void
    {
        $service = $this->createMock(CustomerCartServiceInterface::class);
        $service->expects(self::once())->method('getCart')
            ->with('default', 'assertion', ['price'], null, null)
            ->willReturn([
                'cart' => ['items_count' => 1, 'items' => [['sku' => '24-MB01']]],
                'products' => [['sku' => '24-MB01']],
                'errors' => [],
            ]);

        $result = (new GetCustomerCart($service))->execute([
            'store_code' => 'default',
            'customer_assertion' => 'assertion',
            'sections' => ['price'],
        ]);

        self::assertSame(1, $result['structuredContent']['cart']['items_count']);
        self::assertSame('24-MB01', $result['structuredContent']['products'][0]['sku']);
    }
}
