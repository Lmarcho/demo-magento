<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface CustomerPurchaseHistoryServiceInterface
{
    /**
     * @param string[] $sections
     * @return array{history:array<string,mixed>,products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    public function getHistory(
        string $storeCode,
        string $customerAssertion,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array;
}
