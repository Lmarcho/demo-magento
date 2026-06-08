<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface PromotionServiceInterface
{
    /**
     * @param string[] $skus
     * @param string[] $promotionTypes
     * @return array{store:array<string,mixed>,promotions:array<int,array<string,mixed>>,total:int,returned:int}
     */
    public function getActive(
        string $storeCode,
        array $skus,
        array $promotionTypes,
        ?int $limit = null
    ): array;
}
