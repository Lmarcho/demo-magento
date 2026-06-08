<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Product\AvailabilityResolver;
use Lmarcho\CommerceMcp\Model\Product\MediaResolver;
use Lmarcho\CommerceMcp\Model\Product\PriceResolver;
use Lmarcho\CommerceMcp\Model\Product\VariantImageResolver;
use Lmarcho\CommerceMcp\Model\Product\VariantResolver;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class VariantResolverTest extends TestCase
{
    public function testSimpleProductReturnsEmptyVariantContract(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTypeId'])
            ->getMock();
        $product->method('getTypeId')->willReturn('simple');

        $result = (new VariantResolver(
            $this->createMock(Config::class),
            $this->createMock(PriceResolver::class),
            $this->createMock(VariantImageResolver::class),
            $this->createMock(AvailabilityResolver::class)
        ))->resolve(
            $product,
            new \Lmarcho\CommerceMcp\Model\Store\StoreContext(
                1,
                'default',
                'Default',
                1,
                'base',
                'USD',
                'en_US',
                'UTC',
                'https://example.test/',
                'https://example.test/media/',
                'website',
                'base',
                1
            )
        );

        self::assertSame([], $result['options']);
        self::assertSame([], $result['variants']);
        self::assertFalse($result['truncated']);
    }
}
