<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductHydrator implements ProductHydratorInterface
{
    private const SUPPORTED_SECTIONS = ['core', 'url', 'media', 'price', 'availability'];

    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory,
        private readonly PriceResolver $priceResolver,
        private readonly MediaResolver $mediaResolver,
        private readonly AvailabilityResolver $availabilityResolver
    ) {
    }

    public function hydrate(
        string $storeCode,
        array $skus,
        array $sections,
        ?int $galleryLimit = null
    ): array {
        $skus = $this->normalizeSkus($skus);
        $sections = $this->normalizeSections($sections);
        if (count($skus) > $this->config->getMaxSkusPerRequest()) {
            throw new JsonRpcException(
                'Too many SKUs requested',
                -32602,
                null,
                [
                    'error_code' => 'SKU_LIMIT_EXCEEDED',
                    'maximum' => $this->config->getMaxSkusPerRequest(),
                ]
            );
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);
        $limit = min(
            max(1, $galleryLimit ?? $this->config->getMaxGalleryImages()),
            $this->config->getMaxGalleryImages()
        );

        try {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($context->getStoreId())
                ->addStoreFilter($store)
                ->addAttributeToSelect([
                    'sku',
                    'name',
                    'type_id',
                    'status',
                    'visibility',
                    'price',
                    'special_price',
                    'special_from_date',
                    'special_to_date',
                    'image',
                    'image_label',
                ])
                ->addAttributeToFilter('sku', ['in' => $skus])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ]])
                ->addUrlRewrite()
                ->addPriceData();

            if (in_array('media', $sections, true)) {
                $collection->addMediaGalleryData();
            }

            $loaded = [];
            foreach ($collection as $product) {
                $loaded[$product->getSku()] = $product;
            }
            $availability = in_array('availability', $sections, true)
                ? $this->availabilityResolver->resolve(array_keys($loaded), $context->getStockId())
                : [];

            $products = [];
            $errors = [];
            foreach ($skus as $sku) {
                $product = $loaded[$sku] ?? null;
                if (!$product instanceof Product) {
                    $errors[] = [
                        'sku' => $sku,
                        'code' => 'PRODUCT_NOT_AVAILABLE',
                        'message' => 'Product is not available.',
                    ];
                    continue;
                }

                try {
                    $products[] = $this->serialize(
                        $product,
                        $sections,
                        $context->getCurrency(),
                        $availability[$sku] ?? null,
                        $limit
                    );
                } catch (\Throwable) {
                    $errors[] = [
                        'sku' => $sku,
                        'code' => 'PRODUCT_HYDRATION_FAILED',
                        'message' => 'Product data is temporarily unavailable.',
                    ];
                }
            }

            return ['products' => $products, 'errors' => $errors];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    /**
     * @param string[] $sections
     * @param array{is_salable:?bool,status:string}|null $availability
     * @return array<string,mixed>
     */
    private function serialize(
        Product $product,
        array $sections,
        string $currency,
        ?array $availability,
        int $galleryLimit
    ): array {
        $data = [
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'type' => (string)$product->getTypeId(),
        ];

        if (in_array('url', $sections, true)) {
            $data['url'] = (string)$product->getUrlInStore(['_secure' => true]);
        }
        if (in_array('media', $sections, true)) {
            $data['media'] = $this->mediaResolver->resolve($product, $galleryLimit);
        }
        if (in_array('price', $sections, true)) {
            $data['price'] = $this->priceResolver->resolve($product, $currency);
        }
        if (in_array('availability', $sections, true)) {
            $data['availability'] = $availability
                ?? ['is_salable' => null, 'status' => 'UNKNOWN'];
        }
        if ($product->getTypeId() === 'configurable') {
            $data['capability_notes'] = ['Variants are available in Phase M4.'];
        }

        return $data;
    }

    /**
     * @param mixed[] $skus
     * @return string[]
     */
    private function normalizeSkus(array $skus): array
    {
        $normalized = [];
        foreach ($skus as $sku) {
            if (!is_string($sku) || trim($sku) === '' || strlen($sku) > 64) {
                throw new JsonRpcException(
                    'Invalid SKU list',
                    -32602,
                    null,
                    ['error_code' => 'INVALID_SKU']
                );
            }
            $normalized[] = trim($sku);
        }
        if ($normalized === []) {
            throw new JsonRpcException(
                'At least one SKU is required',
                -32602,
                null,
                ['error_code' => 'SKUS_REQUIRED']
            );
        }
        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed[] $sections
     * @return string[]
     */
    private function normalizeSections(array $sections): array
    {
        if ($sections === []) {
            return self::SUPPORTED_SECTIONS;
        }
        foreach ($sections as $section) {
            if (!is_string($section) || !in_array($section, self::SUPPORTED_SECTIONS, true)) {
                throw new JsonRpcException(
                    'Invalid product section',
                    -32602,
                    null,
                    ['error_code' => 'INVALID_SECTION']
                );
            }
        }
        return array_values(array_unique(array_merge(['core'], $sections)));
    }
}
