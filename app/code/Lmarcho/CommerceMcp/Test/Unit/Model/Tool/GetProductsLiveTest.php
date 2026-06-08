<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetProductsLive;
use PHPUnit\Framework\TestCase;

class GetProductsLiveTest extends TestCase
{
    public function testSchemaRequiresStoreAndSkus(): void
    {
        $tool = new GetProductsLive($this->createMock(ProductHydratorInterface::class));

        self::assertSame(['store_code', 'skus'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsUnknownArguments(): void
    {
        $tool = new GetProductsLive($this->createMock(ProductHydratorInterface::class));

        try {
            $tool->execute([
                'store_code' => 'default',
                'skus' => ['SKU-1'],
                'cost' => true,
            ]);
            self::fail('Expected validation error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('UNKNOWN_ARGUMENT', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsProductsAndPartialErrors(): void
    {
        $hydrator = $this->createMock(ProductHydratorInterface::class);
        $hydrator->expects(self::once())->method('hydrate')
            ->with('default', ['SKU-1', 'MISSING'], ['price'], 2)
            ->willReturn([
                'products' => [['sku' => 'SKU-1']],
                'errors' => [[
                    'sku' => 'MISSING',
                    'code' => 'PRODUCT_NOT_AVAILABLE',
                    'message' => 'Product is not available.',
                ]],
            ]);

        $result = (new GetProductsLive($hydrator))->execute([
            'store_code' => 'default',
            'skus' => ['SKU-1', 'MISSING'],
            'sections' => ['price'],
            'gallery_limit' => 2,
        ]);

        self::assertSame('SKU-1', $result['structuredContent']['products'][0]['sku']);
        self::assertSame(
            'PRODUCT_NOT_AVAILABLE',
            $result['structuredContent']['errors'][0]['code']
        );
        self::assertFalse($result['isError']);
    }
}
