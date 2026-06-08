<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface ProductVariantServiceInterface
{
    /**
     * @return array<string,mixed>
     */
    public function get(string $storeCode, string $sku, ?int $limit = null): array;
}
