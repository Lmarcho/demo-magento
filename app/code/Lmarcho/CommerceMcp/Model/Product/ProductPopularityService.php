<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\ProductPopularityServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class ProductPopularityService implements ProductPopularityServiceInterface
{
    private const MAX_LIMIT = 500;

    public function __construct(
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function get(
        string $storeCode,
        array $skus = [],
        ?int $categoryId = null,
        ?string $query = null,
        int $windowDays = 90,
        ?int $limit = null
    ): array {
        $context = $this->storeContextResolver->resolve($storeCode);
        $limit = min(max(1, $limit ?? self::MAX_LIMIT), self::MAX_LIMIT);
        $windowDays = min(max(0, $windowDays), 3650);
        $skus = $this->normalizeSkus($skus);
        $eligibleSkus = $this->eligibleSkus($context->getStoreId(), $skus, $categoryId, $query);

        if (is_array($eligibleSkus) && $eligibleSkus === []) {
            return [
                'window_days' => $windowDays,
                'total' => 0,
                'returned' => 0,
                'items' => [],
            ];
        }

        $items = $this->aggregateSales($context->getStoreId(), $eligibleSkus, $windowDays, $limit);

        return [
            'window_days' => $windowDays,
            'total' => count($items),
            'returned' => count($items),
            'items' => $items,
        ];
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
                throw $this->invalidArguments('INVALID_SKU');
            }
            $normalized[] = trim($sku);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param string[] $skus
     * @return string[]|null
     */
    private function eligibleSkus(int $storeId, array $skus, ?int $categoryId, ?string $query): ?array
    {
        $query = $this->normalizeQuery($query);
        if ($skus !== [] && $categoryId === null && $query === null) {
            return $skus;
        }

        if ($categoryId === null && $query === null && $skus === []) {
            return null;
        }

        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($storeId);
        $this->storeManager->setCurrentStore($store);

        try {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId)
                ->addStoreFilter($store)
                ->addAttributeToSelect(['sku', 'name'])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ]])
                ->setPageSize(1000)
                ->setCurPage(1);

            if ($skus !== []) {
                $collection->addAttributeToFilter('sku', ['in' => $skus]);
            }

            if ($categoryId !== null) {
                $collection->addCategoryFilter($this->loadPublicCategory($categoryId, $storeId, (int)$store->getRootCategoryId()));
            }

            $eligible = [];
            foreach ($collection as $product) {
                if (!$product instanceof Product || !$this->matchesQuery($product, $query)) {
                    continue;
                }
                $eligible[] = (string)$product->getSku();
            }

            return array_values(array_unique($eligible));
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function aggregateSales(int $storeId, ?array $eligibleSkus, int $windowDays, int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $quantityExpr = 'GREATEST(item.qty_ordered - item.qty_canceled - item.qty_refunded, 0)';

        $select = $connection->select()
            ->from(['item' => $itemTable], [
                'sku' => 'item.sku',
                'purchase_count' => new \Zend_Db_Expr('ROUND(SUM(' . $quantityExpr . '))'),
            ])
            ->join(['sales' => $orderTable], 'sales.entity_id = item.order_id', [])
            ->where('item.parent_item_id IS NULL')
            ->where('sales.store_id = ?', $storeId)
            ->where('sales.state NOT IN (?)', ['canceled'])
            ->group('item.sku')
            ->having('purchase_count > 0')
            ->order('purchase_count DESC')
            ->limit($limit);

        if ($windowDays > 0) {
            $select->where('sales.created_at >= ?', gmdate('Y-m-d H:i:s', time() - ($windowDays * 86400)));
        }

        if (is_array($eligibleSkus)) {
            $select->where('item.sku IN (?)', $eligibleSkus);
        }

        $items = [];
        $rank = 1;
        foreach ($connection->fetchAll($select) as $row) {
            $sku = trim((string)($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $items[] = [
                'rank' => $rank++,
                'sku' => $sku,
                'purchase_count' => max(0, (int)($row['purchase_count'] ?? 0)),
            ];
        }

        return $items;
    }

    private function loadPublicCategory(int $categoryId, int $storeId, int $rootCategoryId): Category
    {
        if ($categoryId < 1) {
            throw $this->invalidArguments('INVALID_CATEGORY_ID');
        }

        try {
            $category = $this->categoryRepository->get($categoryId, $storeId);
        } catch (NoSuchEntityException) {
            throw $this->invalidArguments('CATEGORY_NOT_FOUND');
        }
        if (!$category instanceof Category
            || !$category->getIsActive()
            || !in_array($rootCategoryId, array_map('intval', $category->getPathIds()), true)
        ) {
            throw $this->invalidArguments('CATEGORY_NOT_AVAILABLE');
        }

        return $category;
    }

    private function normalizeQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }
        $query = trim($query);
        if ($query === '') {
            return null;
        }
        if (mb_strlen($query) > 128) {
            throw $this->invalidArguments('INVALID_QUERY');
        }

        return $query;
    }

    private function matchesQuery(Product $product, ?string $query): bool
    {
        if ($query === null) {
            return true;
        }

        $needle = mb_strtolower($query);

        return str_contains(mb_strtolower((string)$product->getSku()), $needle)
            || str_contains(mb_strtolower((string)$product->getName()), $needle);
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid product popularity arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
