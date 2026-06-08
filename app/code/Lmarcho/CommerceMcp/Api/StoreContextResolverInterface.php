<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

use Lmarcho\CommerceMcp\Api\Data\StoreContextInterface;

interface StoreContextResolverInterface
{
    public function resolve(string $storeCode): StoreContextInterface;
}
