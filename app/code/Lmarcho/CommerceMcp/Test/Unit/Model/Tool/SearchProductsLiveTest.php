<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductSearchServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\SearchProductsLive;
use PHPUnit\Framework\TestCase;

class SearchProductsLiveTest extends TestCase
{
    public function testSchemaRequiresStoreOnly(): void
    {
        $tool = new SearchProductsLive(
            $this->createMock(ProductSearchServiceInterface::class)
        );

        self::assertSame(['store_code'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $tool = new SearchProductsLive(
            $this->createMock(ProductSearchServiceInterface::class)
        );

        try {
            $tool->execute(['store_code' => 'default', 'query' => 'bag', 'limit' => 0]);
            self::fail('Expected invalid limit error.');
        } catch (JsonRpcException $exception) {
            self::assertSame(
                'INVALID_SEARCH_LIMIT',
                $exception->getErrorData()['error_code']
            );
        }
    }

    public function testReturnsStructuredSearchData(): void
    {
        $service = $this->createMock(ProductSearchServiceInterface::class);
        $service->expects(self::once())->method('search')
            ->with('default', 'bag', ['24-MB01'], ['price'], 2, 1, null)
            ->willReturn([
                'query' => 'bag',
                'total' => 1,
                'returned' => 1,
                'products' => [['sku' => '24-MB01']],
                'errors' => [],
            ]);

        $result = (new SearchProductsLive($service))->execute([
            'store_code' => 'default',
            'query' => 'bag',
            'candidate_skus' => ['24-MB01'],
            'sections' => ['price'],
            'limit' => 2,
            'gallery_limit' => 1,
        ]);

        self::assertSame('bag', $result['structuredContent']['query']);
        self::assertSame('24-MB01', $result['structuredContent']['products'][0]['sku']);
        self::assertFalse($result['isError']);
    }
}
