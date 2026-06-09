<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface CustomerCartServiceInterface
{
    /**
     * @param string[] $sections
     * @return array{cart:array<string,mixed>,products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    public function getCart(
        string $storeCode,
        string $customerAssertion,
        array $sections,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array;
}
