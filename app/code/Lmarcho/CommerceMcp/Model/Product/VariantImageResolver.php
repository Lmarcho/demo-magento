<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;

class VariantImageResolver
{
    public function __construct(
        private readonly Config $config,
        private readonly MediaResolver $mediaResolver
    ) {
    }

    /**
     * @return array{primary_image:?array<string,mixed>,image_fallback:bool}
     */
    public function resolve(Product $child, Product $parent): array
    {
        $childImage = $this->mediaResolver->resolve($child, 1)['primary_image'];
        if ($childImage !== null) {
            return ['primary_image' => $childImage, 'image_fallback' => false];
        }
        if (!$this->config->isVariantImageFallbackEnabled()) {
            return ['primary_image' => null, 'image_fallback' => false];
        }

        $parentImage = $this->mediaResolver->resolve($parent, 1)['primary_image'];

        return [
            'primary_image' => $parentImage,
            'image_fallback' => $parentImage !== null,
        ];
    }
}
