<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface CategoryProductServiceInterface
{
    /**
     * @param string[] $sections
     * @return array{category:array<string,mixed>,total:int,returned:int,products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    public function getProducts(
        string $storeCode,
        int $categoryId,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array;
}
