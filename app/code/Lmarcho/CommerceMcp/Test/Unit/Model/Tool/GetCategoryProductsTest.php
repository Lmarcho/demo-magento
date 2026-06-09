<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\CategoryProductServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetCategoryProducts;
use PHPUnit\Framework\TestCase;

class GetCategoryProductsTest extends TestCase
{
    public function testSchemaRequiresStoreAndCategory(): void
    {
        $tool = new GetCategoryProducts($this->createMock(CategoryProductServiceInterface::class));

        self::assertSame(['store_code', 'category_id'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsInvalidCategoryId(): void
    {
        $tool = new GetCategoryProducts($this->createMock(CategoryProductServiceInterface::class));

        try {
            $tool->execute(['store_code' => 'default', 'category_id' => 0]);
            self::fail('Expected invalid category id error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('INVALID_CATEGORY_ID', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredCategoryProducts(): void
    {
        $service = $this->createMock(CategoryProductServiceInterface::class);
        $service->expects(self::once())->method('getProducts')
            ->with('default', 12, ['price'], 2, null, null)
            ->willReturn([
                'category' => ['id' => 12, 'name' => 'Bags'],
                'total' => 1,
                'returned' => 1,
                'products' => [['sku' => '24-MB01']],
                'errors' => [],
            ]);

        $result = (new GetCategoryProducts($service))->execute([
            'store_code' => 'default',
            'category_id' => 12,
            'sections' => ['price'],
            'limit' => 2,
        ]);

        self::assertSame(12, $result['structuredContent']['category']['id']);
        self::assertSame('24-MB01', $result['structuredContent']['products'][0]['sku']);
    }
}
