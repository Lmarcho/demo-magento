<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Magento\InventorySalesApi\Api\AreProductsSalableInterface;

class AvailabilityResolver
{
    public function __construct(private readonly AreProductsSalableInterface $areProductsSalable)
    {
    }

    /**
     * @param string[] $skus
     * @return array<string,array{is_salable:?bool,status:string}>
     */
    public function resolve(array $skus, int $stockId): array
    {
        $availability = [];
        foreach ($skus as $sku) {
            $availability[$sku] = ['is_salable' => null, 'status' => 'UNKNOWN'];
        }

        try {
            foreach ($this->areProductsSalable->execute($skus, $stockId) as $result) {
                $availability[$result->getSku()] = [
                    'is_salable' => $result->isSalable(),
                    'status' => $result->isSalable() ? 'IN_STOCK' : 'OUT_OF_STOCK',
                ];
            }
        } catch (\Throwable) {
            return $availability;
        }

        return $availability;
    }
}
