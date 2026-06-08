<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductVariantServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetProductVariants;
use PHPUnit\Framework\TestCase;

class GetProductVariantsTest extends TestCase
{
    public function testSchemaRequiresStoreAndSku(): void
    {
        $tool = new GetProductVariants(
            $this->createMock(ProductVariantServiceInterface::class)
        );

        self::assertSame(['store_code', 'sku'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $tool = new GetProductVariants(
            $this->createMock(ProductVariantServiceInterface::class)
        );

        try {
            $tool->execute(['store_code' => 'default', 'sku' => 'MH01', 'limit' => 0]);
            self::fail('Expected invalid limit error.');
        } catch (JsonRpcException $exception) {
            self::assertSame(
                'INVALID_VARIANT_LIMIT',
                $exception->getErrorData()['error_code']
            );
        }
    }

    public function testReturnsStructuredVariantData(): void
    {
        $service = $this->createMock(ProductVariantServiceInterface::class);
        $service->expects(self::once())->method('get')->with('default', 'MH01', 2)
            ->willReturn([
                'product' => ['sku' => 'MH01', 'name' => 'Hoodie', 'type' => 'configurable'],
                'options' => [['code' => 'size', 'label' => 'Size', 'values' => []]],
                'variants' => [['sku' => 'MH01-S']],
                'total' => 3,
                'returned' => 1,
                'truncated' => true,
            ]);

        $result = (new GetProductVariants($service))->execute([
            'store_code' => 'default',
            'sku' => 'MH01',
            'limit' => 2,
        ]);

        self::assertSame('MH01', $result['structuredContent']['product']['sku']);
        self::assertTrue($result['structuredContent']['truncated']);
        self::assertFalse($result['isError']);
    }
}
