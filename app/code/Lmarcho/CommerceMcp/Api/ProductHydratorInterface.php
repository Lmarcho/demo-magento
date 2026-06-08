<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface ProductHydratorInterface
{
    /**
     * @param string[] $skus
     * @param string[] $sections
     * @return array{products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    public function hydrate(
        string $storeCode,
        array $skus,
        array $sections,
        ?int $galleryLimit = null
    ): array;
}
