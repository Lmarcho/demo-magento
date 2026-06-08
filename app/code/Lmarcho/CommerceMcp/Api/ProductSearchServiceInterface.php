<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface ProductSearchServiceInterface
{
    /**
     * @param string[] $candidateSkus
     * @param string[] $sections
     * @return array{query:?string,total:int,returned:int,products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    public function search(
        string $storeCode,
        ?string $query,
        array $candidateSkus,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array;
}
