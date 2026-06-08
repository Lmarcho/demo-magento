<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\ProductSearchServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\Search\CollectionFactory as SearchCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductSearchService implements ProductSearchServiceInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly SearchCollectionFactory $searchCollectionFactory,
        private readonly ProductHydratorInterface $productHydrator
    ) {
    }

    public function search(
        string $storeCode,
        ?string $query,
        array $candidateSkus,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array {
        $query = $this->normalizeQuery($query);
        $candidateSkus = $this->normalizeCandidateSkus($candidateSkus);
        if ($query === null && $candidateSkus === []) {
            throw new JsonRpcException(
                'Search query or candidate SKUs are required',
                -32602,
                null,
                ['error_code' => 'SEARCH_INPUT_REQUIRED']
            );
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);
        $limit = min(
            max(1, $limit ?? $this->config->getMaxSearchResults()),
            $this->config->getMaxSearchResults()
        );

        try {
            $searchResult = $candidateSkus !== []
                ? $this->filterCandidateSkus($candidateSkus, $query, $context->getStoreId(), $store, $limit)
                : $this->nativeSearch($query ?? '', $context->getStoreId(), $store, $limit);

            $hydrated = $searchResult['skus'] === []
                ? ['products' => [], 'errors' => []]
                : $this->productHydrator->hydrate(
                    $storeCode,
                    $searchResult['skus'],
                    $sections,
                    $galleryLimit,
                    $variantLimit
                );

            return [
                'query' => $query,
                'total' => $searchResult['total'],
                'returned' => count($hydrated['products']),
                'products' => $hydrated['products'],
                'errors' => $hydrated['errors'],
            ];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    /**
     * @param string[] $candidateSkus
     * @return array{total:int,skus:string[]}
     */
    private function filterCandidateSkus(
        array $candidateSkus,
        ?string $query,
        int $storeId,
        mixed $store,
        int $limit
    ): array {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'name'])
            ->addAttributeToFilter('sku', ['in' => $candidateSkus])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]]);

        $loaded = [];
        foreach ($collection as $product) {
            if (!$product instanceof Product || !$this->matchesQuery($product, $query)) {
                continue;
            }
            $loaded[(string)$product->getSku()] = true;
        }

        $matched = array_values(array_filter(
            $candidateSkus,
            static fn(string $sku): bool => isset($loaded[$sku])
        ));

        return [
            'total' => count($matched),
            'skus' => array_slice($matched, 0, $limit),
        ];
    }

    /**
     * @return array{total:int,skus:string[]}
     */
    private function nativeSearch(string $query, int $storeId, mixed $store, int $limit): array
    {
        $collection = $this->searchCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'name'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]])
            ->addSearchFilter($query)
            ->setPageSize($limit)
            ->setCurPage(1);

        $total = (int)$collection->getSize();
        $skus = [];
        foreach ($collection as $product) {
            if ($product instanceof Product) {
                $skus[] = (string)$product->getSku();
            }
        }

        return ['total' => $total, 'skus' => $skus];
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
            throw new JsonRpcException(
                'Search query is too long',
                -32602,
                null,
                ['error_code' => 'INVALID_SEARCH_QUERY']
            );
        }

        return $query;
    }

    /**
     * @param mixed[] $candidateSkus
     * @return string[]
     */
    private function normalizeCandidateSkus(array $candidateSkus): array
    {
        $normalized = [];
        foreach ($candidateSkus as $sku) {
            if (!is_string($sku) || trim($sku) === '' || strlen($sku) > 64) {
                throw new JsonRpcException(
                    'Invalid candidate SKU list',
                    -32602,
                    null,
                    ['error_code' => 'INVALID_CANDIDATE_SKU']
                );
            }
            $normalized[] = trim($sku);
        }
        $normalized = array_values(array_unique($normalized));
        if (count($normalized) > $this->config->getMaxSkusPerRequest()) {
            throw new JsonRpcException(
                'Too many candidate SKUs requested',
                -32602,
                null,
                [
                    'error_code' => 'CANDIDATE_SKU_LIMIT_EXCEEDED',
                    'maximum' => $this->config->getMaxSkusPerRequest(),
                ]
            );
        }

        return $normalized;
    }
}
