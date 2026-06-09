<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Customer;

use Lmarcho\CommerceMcp\Api\CustomerAssertionServiceInterface;
use Lmarcho\CommerceMcp\Api\CustomerPurchaseHistoryServiceInterface;
use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Product\VisibleProductSkuResolver;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class CustomerPurchaseHistoryService implements CustomerPurchaseHistoryServiceInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly CustomerAssertionServiceInterface $customerAssertionService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly VisibleProductSkuResolver $visibleProductSkuResolver,
        private readonly ProductHydratorInterface $productHydrator
    ) {
    }

    public function getHistory(
        string $storeCode,
        string $customerAssertion,
        array $sections,
        ?int $limit = null,
        ?int $galleryLimit = null,
        ?int $variantLimit = null
    ): array {
        if (trim($customerAssertion) === '') {
            throw $this->invalidArguments('CUSTOMER_ASSERTION_REQUIRED');
        }
        $context = $this->storeContextResolver->resolve($storeCode);
        $claims = $this->customerAssertionService->verify(
            $customerAssertion,
            $context->getStoreId(),
            $context->getWebsiteId()
        );
        $limit = min(
            max(1, $limit ?? $this->config->getMaxSearchResults()),
            min($this->config->getMaxSearchResults(), $this->config->getMaxSkusPerRequest())
        );

        $items = $this->collectPurchasedProducts($claims['customer_id'], $context->getStoreId(), $limit);
        $skus = array_values(array_unique(array_column($items, 'product_sku')));
        $hydrated = $skus === []
            ? ['products' => [], 'errors' => []]
            : $this->productHydrator->hydrate($storeCode, $skus, $sections, $galleryLimit, $variantLimit);

        return [
            'history' => [
                'returned' => count($items),
                'items' => $items,
            ],
            'products' => $hydrated['products'],
            'errors' => $hydrated['errors'],
        ];
    }

    /**
     * @return array<int,array{sku:string,product_sku:string,name:string,total_quantity:float,order_count:int,last_purchased_at:?string}>
     */
    private function collectPurchasedProducts(int $customerId, int $storeId, int $limit): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('created_at')
            ->setDescendingDirection()
            ->create();
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addFilter('store_id', $storeId)
            ->setPageSize(25)
            ->setCurrentPage(1)
            ->setSortOrders([$sortOrder])
            ->create();

        $bySku = [];
        foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
            if (!$order instanceof Order) {
                continue;
            }
            foreach ($order->getAllVisibleItems() as $item) {
                $sku = (string)$item->getSku();
                if ($sku === '') {
                    continue;
                }
                $productSku = $this->visibleProductSkuResolver->resolve($sku, $storeId);
                $bySku[$sku] ??= [
                    'sku' => $sku,
                    'product_sku' => $productSku,
                    'name' => (string)$item->getName(),
                    'total_quantity' => 0.0,
                    'order_count' => 0,
                    'last_purchased_at' => $this->dateToUtc($order->getCreatedAt()),
                ];
                $bySku[$sku]['total_quantity'] += (float)$item->getQtyOrdered();
                $bySku[$sku]['order_count']++;
                if (count($bySku) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($bySku);
    }

    private function dateToUtc(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return gmdate('c', strtotime($date) ?: time());
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid purchase history arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
