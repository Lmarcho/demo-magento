<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\RelatedProductServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetRelatedProducts;
use PHPUnit\Framework\TestCase;

class GetRelatedProductsTest extends TestCase
{
    public function testSchemaRequiresStoreAndSku(): void
    {
        $tool = new GetRelatedProducts(
            $this->createMock(RelatedProductServiceInterface::class)
        );

        self::assertSame(['store_code', 'sku'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsUnknownArguments(): void
    {
        $tool = new GetRelatedProducts(
            $this->createMock(RelatedProductServiceInterface::class)
        );

        try {
            $tool->execute(['store_code' => 'default', 'sku' => '24-MB01', 'recursive' => true]);
            self::fail('Expected unknown argument error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('UNKNOWN_ARGUMENT', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredRelatedData(): void
    {
        $service = $this->createMock(RelatedProductServiceInterface::class);
        $service->expects(self::once())->method('get')
            ->with('default', '24-MB01', ['related'], ['price'], 2, null, null)
            ->willReturn([
                'source_product' => ['sku' => '24-MB01', 'name' => 'Bag', 'type' => 'simple'],
                'groups' => [
                    'related' => [
                        'total' => 1,
                        'returned' => 1,
                        'products' => [['sku' => '24-MB02', 'link_position' => 1]],
                        'errors' => [],
                    ],
                ],
            ]);

        $result = (new GetRelatedProducts($service))->execute([
            'store_code' => 'default',
            'sku' => '24-MB01',
            'link_types' => ['related'],
            'sections' => ['price'],
            'limit' => 2,
        ]);

        self::assertSame('24-MB01', $result['structuredContent']['source_product']['sku']);
        self::assertSame('24-MB02', $result['structuredContent']['groups']['related']['products'][0]['sku']);
        self::assertFalse($result['isError']);
    }
}
