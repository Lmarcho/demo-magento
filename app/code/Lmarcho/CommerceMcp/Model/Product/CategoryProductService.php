<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\CategoryProductServiceInterface;
use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryProductService implements CategoryProductServiceInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductHydratorInterface $productHydrator
    ) {
    }

    public function getProducts(
        string $storeCode,
        int $categoryId,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array {
        if ($categoryId < 1) {
            throw $this->invalidArguments('INVALID_CATEGORY_ID');
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);
        $limit = min(max(1, $limit ?? $this->config->getMaxSearchResults()), $this->config->getMaxSearchResults());

        try {
            $category = $this->loadPublicCategory($categoryId, $context->getStoreId(), (int)$store->getRootCategoryId());
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($context->getStoreId())
                ->addStoreFilter($store)
                ->addAttributeToSelect(['sku', 'name'])
                ->addCategoryFilter($category)
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_BOTH,
                ]])
                ->addAttributeToSort('position')
                ->setPageSize($limit)
                ->setCurPage(1);

            $total = (int)$collection->getSize();
            $skus = [];
            foreach ($collection as $product) {
                if ($product instanceof Product) {
                    $skus[] = (string)$product->getSku();
                }
            }

            $hydrated = $skus === []
                ? ['products' => [], 'errors' => []]
                : $this->productHydrator->hydrate($storeCode, $skus, $sections, $galleryLimit, $variantLimit);

            return [
                'category' => [
                    'id' => (int)$category->getId(),
                    'name' => (string)$category->getName(),
                    'url_key' => (string)$category->getUrlKey(),
                ],
                'total' => $total,
                'returned' => count($hydrated['products']),
                'products' => $hydrated['products'],
                'errors' => $hydrated['errors'],
            ];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    private function loadPublicCategory(int $categoryId, int $storeId, int $rootCategoryId): Category
    {
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

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid category product arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
