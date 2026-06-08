<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Tax\Helper\Data as TaxHelper;

class PriceResolver
{
    public function __construct(private readonly TaxHelper $taxHelper)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function resolve(Product $product, string $currency): array
    {
        $priceInfo = $product->getPriceInfo();
        $regularPriceModel = $priceInfo->getPrice(RegularPrice::PRICE_CODE);
        $regularPrice = (float)$regularPriceModel->getAmount()->getValue();
        $finalPriceModel = $priceInfo->getPrice(FinalPrice::PRICE_CODE);
        $finalPrice = (float)$finalPriceModel->getAmount()->getValue();
        $minimumPrice = (float)$finalPriceModel->getMinimalPrice()->getValue();
        $maximumPrice = (float)$finalPriceModel->getMaximalPrice()->getValue();
        $discountAmount = max(0.0, $regularPrice - $finalPrice);

        return [
            'currency' => $currency,
            'regular_price' => $this->round($regularPrice),
            'final_price' => $this->round($finalPrice),
            'minimum_price' => $this->round($minimumPrice),
            'maximum_price' => $this->round($maximumPrice),
            'discount_amount' => $this->round($discountAmount),
            'discount_percent' => $regularPrice > 0 && $discountAmount > 0
                ? round(($discountAmount / $regularPrice) * 100, 2)
                : 0.0,
            'tax_display' => $this->taxDisplayMode(),
        ];
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }

    private function taxDisplayMode(): string
    {
        if ($this->taxHelper->displayBothPrices()) {
            return 'BOTH';
        }
        if ($this->taxHelper->displayPriceIncludingTax()) {
            return 'INCLUDING_TAX';
        }
        return 'EXCLUDING_TAX';
    }
}
