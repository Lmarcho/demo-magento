<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Store\Model\StoreManagerInterface;

class VisibleProductSkuResolver
{
    /**
     * @var array<string,string>
     */
    private array $cache = [];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory,
        private readonly Configurable $configurable
    ) {
    }

    public function resolve(string $sku, int $storeId): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return $sku;
        }

        $cacheKey = $storeId . '|' . $sku;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $visibleProduct = $this->loadVisibleBySku($sku, $storeId);
            if ($visibleProduct instanceof Product) {
                return $this->cache[$cacheKey] = (string)$visibleProduct->getSku();
            }

            $purchasedProduct = $this->loadBySku($sku, $storeId);
            if (!$purchasedProduct instanceof Product) {
                return $this->cache[$cacheKey] = $sku;
            }

            $parentIds = array_values(array_filter(array_map(
                static fn($parentId): int => (int)$parentId,
                $this->configurable->getParentIdsByChild((int)$purchasedProduct->getId())
            )));
            if ($parentIds === []) {
                return $this->cache[$cacheKey] = $sku;
            }

            $parent = $this->loadVisibleByIds($parentIds, $storeId);

            return $this->cache[$cacheKey] = $parent instanceof Product
                ? (string)$parent->getSku()
                : $sku;
        } catch (\Throwable) {
            return $this->cache[$cacheKey] = $sku;
        }
    }

    private function loadVisibleBySku(string $sku, int $storeId): ?Product
    {
        $collection = $this->createVisibleCollection($storeId);
        $collection->addAttributeToFilter('sku', $sku)
            ->setPageSize(1);
        $product = $collection->getFirstItem();

        return $product instanceof Product && $product->getId() ? $product : null;
    }

    /**
     * @param int[] $productIds
     */
    private function loadVisibleByIds(array $productIds, int $storeId): ?Product
    {
        $collection = $this->createVisibleCollection($storeId);
        $collection->addAttributeToFilter('entity_id', ['in' => $productIds])
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(1);
        $product = $collection->getFirstItem();

        return $product instanceof Product && $product->getId() ? $product : null;
    }

    private function loadBySku(string $sku, int $storeId): ?Product
    {
        $store = $this->storeManager->getStore($storeId);
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'status', 'visibility', 'type_id'])
            ->addAttributeToFilter('sku', $sku)
            ->setPageSize(1);
        $product = $collection->getFirstItem();

        return $product instanceof Product && $product->getId() ? $product : null;
    }

    private function createVisibleCollection(int $storeId): Collection
    {
        $store = $this->storeManager->getStore($storeId);
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'status', 'visibility', 'type_id'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]]);

        return $collection;
    }
}
