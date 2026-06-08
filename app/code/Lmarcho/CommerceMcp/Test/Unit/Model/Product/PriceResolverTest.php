<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Product\PriceResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use PHPUnit\Framework\TestCase;

class PriceResolverTest extends TestCase
{
    public function testCalculatesDiscountAndTaxMetadata(): void
    {
        $regular = $this->createMock(PriceInterface::class);
        $regular->method('getValue')->willReturn(100.0);
        $regularAmount = $this->createMock(AmountInterface::class);
        $regularAmount->method('getValue')->willReturn(100.0);
        $regular->method('getAmount')->willReturn($regularAmount);
        $minimum = $this->createMock(AmountInterface::class);
        $minimum->method('getValue')->willReturn(80.0);
        $maximum = $this->createMock(AmountInterface::class);
        $maximum->method('getValue')->willReturn(80.0);
        $final = $this->getMockBuilder(FinalPrice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue', 'getAmount', 'getMinimalPrice', 'getMaximalPrice'])
            ->getMock();
        $final->method('getValue')->willReturn(80.0);
        $finalAmount = $this->createMock(AmountInterface::class);
        $finalAmount->method('getValue')->willReturn(80.0);
        $final->method('getAmount')->willReturn($finalAmount);
        $final->method('getMinimalPrice')->willReturn($minimum);
        $final->method('getMaximalPrice')->willReturn($maximum);
        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturnMap([
            ['regular_price', $regular],
            ['final_price', $final],
        ]);
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPriceInfo'])
            ->getMock();
        $product->method('getPriceInfo')->willReturn($priceInfo);
        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('displayBothPrices')->willReturn(false);
        $taxHelper->method('displayPriceIncludingTax')->willReturn(true);

        $price = (new PriceResolver($taxHelper))->resolve($product, 'USD');

        self::assertSame(20.0, $price['discount_amount']);
        self::assertSame(20.0, $price['discount_percent']);
        self::assertSame('INCLUDING_TAX', $price['tax_display']);
    }
}
