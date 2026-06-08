<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\RelatedProductServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class RelatedProductService implements RelatedProductServiceInterface
{
    private const LINK_TYPES = ['related', 'upsell', 'crosssell'];

    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductHydratorInterface $productHydrator
    ) {
    }

    public function get(
        string $storeCode,
        string $sku,
        array $linkTypes,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array {
        $sku = trim($sku);
        if ($sku === '' || strlen($sku) > 64) {
            throw new JsonRpcException(
                'Invalid source product SKU',
                -32602,
                null,
                ['error_code' => 'INVALID_SKU']
            );
        }
        $linkTypes = $this->normalizeLinkTypes($linkTypes);

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);
        $limit = min(
            max(1, $limit ?? $this->config->getMaxRelatedProducts()),
            $this->config->getMaxRelatedProducts()
        );

        try {
            $source = $this->loadSourceProduct($sku, $context->getStoreId(), $store);
            $groups = [];
            foreach ($linkTypes as $linkType) {
                $linked = $this->loadLinkedSkus($source, $linkType, $context->getStoreId(), $store, $limit);
                $hydrated = $linked['skus'] === []
                    ? ['products' => [], 'errors' => []]
                    : $this->productHydrator->hydrate(
                        $storeCode,
                        $linked['skus'],
                        $sections,
                        $galleryLimit,
                        $variantLimit
                    );
                $groups[$linkType] = [
                    'total' => $linked['total'],
                    'returned' => count($hydrated['products']),
                    'products' => $this->attachPositions($hydrated['products'], $linked['positions']),
                    'errors' => $hydrated['errors'],
                ];
            }

            return [
                'source_product' => [
                    'sku' => (string)$source->getSku(),
                    'name' => (string)$source->getName(),
                    'type' => (string)$source->getTypeId(),
                ],
                'groups' => $groups,
            ];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    private function loadSourceProduct(string $sku, int $storeId, mixed $store): Product
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'name', 'type_id', 'status', 'visibility'])
            ->addAttributeToFilter('sku', $sku)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]])
            ->setPageSize(1);
        $product = $collection->getFirstItem();
        if (!$product instanceof Product || !$product->getId()) {
            throw new JsonRpcException(
                'Product is not available',
                -32602,
                null,
                ['error_code' => 'PRODUCT_NOT_AVAILABLE']
            );
        }

        return $product;
    }

    /**
     * @return array{total:int,skus:string[],positions:array<string,int|null>}
     */
    private function loadLinkedSkus(
        Product $source,
        string $linkType,
        int $storeId,
        mixed $store,
        int $limit
    ): array {
        $collection = match ($linkType) {
            'upsell' => $source->getUpSellProductCollection(),
            'crosssell' => $source->getCrossSellProductCollection(),
            default => $source->getRelatedProductCollection(),
        };
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'name', 'status', 'visibility'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]])
            ->setPositionOrder();

        $allSkus = [];
        $positions = [];
        foreach ($collection as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            $sku = (string)$product->getSku();
            $allSkus[] = $sku;
            $position = $product->getData('position');
            $positions[$sku] = is_numeric($position) ? (int)$position : null;
        }
        $skus = array_slice($allSkus, 0, $limit);
        $positions = array_intersect_key($positions, array_flip($skus));

        return ['total' => count($allSkus), 'skus' => $skus, 'positions' => $positions];
    }

    /**
     * @param array<int,array<string,mixed>> $products
     * @param array<string,int|null> $positions
     * @return array<int,array<string,mixed>>
     */
    private function attachPositions(array $products, array $positions): array
    {
        foreach ($products as &$product) {
            $sku = (string)($product['sku'] ?? '');
            $product['link_position'] = $positions[$sku] ?? null;
        }
        unset($product);

        return $products;
    }

    /**
     * @param mixed[] $linkTypes
     * @return string[]
     */
    private function normalizeLinkTypes(array $linkTypes): array
    {
        if ($linkTypes === []) {
            return self::LINK_TYPES;
        }
        foreach ($linkTypes as $linkType) {
            if (!is_string($linkType) || !in_array($linkType, self::LINK_TYPES, true)) {
                throw new JsonRpcException(
                    'Invalid related product link type',
                    -32602,
                    null,
                    ['error_code' => 'INVALID_LINK_TYPE']
                );
            }
        }

        return array_values(array_unique($linkTypes));
    }
}
