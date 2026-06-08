<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface RelatedProductServiceInterface
{
    /**
     * @param string[] $linkTypes
     * @param string[] $sections
     * @return array{source_product:array<string,mixed>,groups:array<string,array<string,mixed>>}
     */
    public function get(
        string $storeCode,
        string $sku,
        array $linkTypes,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array;
}
