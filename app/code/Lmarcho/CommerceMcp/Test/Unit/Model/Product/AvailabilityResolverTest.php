<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Product\AvailabilityResolver;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use PHPUnit\Framework\TestCase;

class AvailabilityResolverTest extends TestCase
{
    public function testMapsBulkSalabilityResults(): void
    {
        $result = $this->createMock(IsProductSalableResultInterface::class);
        $result->method('getSku')->willReturn('SKU-1');
        $result->method('isSalable')->willReturn(true);
        $service = $this->createMock(AreProductsSalableInterface::class);
        $service->expects(self::once())->method('execute')->with(['SKU-1'], 3)
            ->willReturn([$result]);

        self::assertSame([
            'SKU-1' => ['is_salable' => true, 'status' => 'IN_STOCK'],
        ], (new AvailabilityResolver($service))->resolve(['SKU-1'], 3));
    }

    public function testServiceFailureReturnsUnknown(): void
    {
        $service = $this->createMock(AreProductsSalableInterface::class);
        $service->method('execute')->willThrowException(new \RuntimeException('failed'));

        self::assertSame([
            'SKU-1' => ['is_salable' => null, 'status' => 'UNKNOWN'],
        ], (new AvailabilityResolver($service))->resolve(['SKU-1'], 3));
    }
}
