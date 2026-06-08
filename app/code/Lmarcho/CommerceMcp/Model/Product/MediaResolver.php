<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class MediaResolver
{
    private const NO_SELECTION = 'no_selection';

    public function __construct(
        private readonly MediaConfig $mediaConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array{primary_image:?array<string,mixed>,gallery:array<int,array<string,mixed>>}
     */
    public function resolve(Product $product, int $galleryLimit): array
    {
        $primaryFile = $this->normalizeFile((string)$product->getImage());
        $primary = $primaryFile === null ? null : [
            'url' => $this->getMediaUrl($primaryFile),
            'label' => (string)($product->getData('image_label') ?: $product->getName()),
        ];

        $gallery = [];
        foreach ($product->getMediaGalleryImages() ?: [] as $image) {
            if (count($gallery) >= $galleryLimit) {
                break;
            }
            $file = $this->normalizeFile((string)$image->getFile());
            if ($file === null || (bool)$image->getDisabled()) {
                continue;
            }
            $gallery[] = [
                'url' => $this->getMediaUrl($file),
                'label' => (string)($image->getLabel() ?: ''),
                'position' => (int)$image->getPosition(),
                'is_primary' => $primaryFile !== null && $file === $primaryFile,
            ];
        }

        return ['primary_image' => $primary, 'gallery' => $gallery];
    }

    private function getMediaUrl(string $file): string
    {
        $baseUrl = (string)$this->storeManager->getStore()
            ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true);

        return rtrim($baseUrl, '/') . '/' . ltrim($this->mediaConfig->getMediaShortUrl($file), '/');
    }

    private function normalizeFile(string $file): ?string
    {
        $file = trim($file);
        return $file === '' || $file === self::NO_SELECTION ? null : $file;
    }
}
