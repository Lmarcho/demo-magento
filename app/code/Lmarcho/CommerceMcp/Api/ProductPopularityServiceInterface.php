<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface ProductPopularityServiceInterface
{
    /**
     * @param string[] $skus
     * @return array{window_days:int,total:int,returned:int,items:array<int,array<string,mixed>>}
     */
    public function get(
        string $storeCode,
        array $skus = [],
        ?int $categoryId = null,
        ?string $query = null,
        int $windowDays = 90,
        ?int $limit = null
    ): array;
}
