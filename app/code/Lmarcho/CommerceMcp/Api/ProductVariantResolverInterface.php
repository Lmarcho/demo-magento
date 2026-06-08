<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

use Lmarcho\CommerceMcp\Api\Data\StoreContextInterface;
use Magento\Catalog\Model\Product;

interface ProductVariantResolverInterface
{
    /**
     * @return array{
     *   options:array<int,array<string,mixed>>,
     *   variants:array<int,array<string,mixed>>,
     *   total:int,
     *   returned:int,
     *   truncated:bool
     * }
     */
    public function resolve(
        Product $parent,
        StoreContextInterface $storeContext,
        ?int $requestedLimit = null
    ): array;
}
