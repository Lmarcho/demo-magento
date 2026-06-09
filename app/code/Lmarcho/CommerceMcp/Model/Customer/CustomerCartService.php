<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Customer;

use Lmarcho\CommerceMcp\Api\CustomerAssertionServiceInterface;
use Lmarcho\CommerceMcp\Api\CustomerCartServiceInterface;
use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Product\VisibleProductSkuResolver;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

class CustomerCartService implements CustomerCartServiceInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly CustomerAssertionServiceInterface $customerAssertionService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly VisibleProductSkuResolver $visibleProductSkuResolver,
        private readonly ProductHydratorInterface $productHydrator
    ) {
    }

    public function getCart(
        string $storeCode,
        string $customerAssertion,
        array $sections,
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

        try {
            $quote = $this->cartRepository->getActiveForCustomer($claims['customer_id'], [$context->getStoreId()]);
        } catch (NoSuchEntityException) {
            return $this->emptyCart();
        }
        if (!$quote instanceof Quote || (int)$quote->getStoreId() !== $context->getStoreId()) {
            return $this->emptyCart();
        }

        $items = [];
        $skus = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $sku = (string)$item->getSku();
            if ($sku === '') {
                continue;
            }
            $productSku = $this->visibleProductSkuResolver->resolve($sku, $context->getStoreId());
            $items[] = [
                'sku' => $sku,
                'product_sku' => $productSku,
                'name' => (string)$item->getName(),
                'quantity' => (float)$item->getQty(),
                'currency' => (string)$quote->getQuoteCurrencyCode(),
                'price' => round((float)$item->getCalculationPrice(), 2),
                'row_total' => round((float)$item->getRowTotal(), 2),
            ];
            $skus[] = $productSku;
        }
        $skus = array_slice(array_values(array_unique($skus)), 0, $this->config->getMaxSkusPerRequest());
        $hydrated = $skus === []
            ? ['products' => [], 'errors' => []]
            : $this->productHydrator->hydrate($storeCode, $skus, $sections, $galleryLimit, $variantLimit);

        return [
            'cart' => [
                'items_count' => count($items),
                'items_qty' => (float)$quote->getItemsQty(),
                'currency' => (string)$quote->getQuoteCurrencyCode(),
                'items' => $items,
            ],
            'products' => $hydrated['products'],
            'errors' => $hydrated['errors'],
        ];
    }

    /**
     * @return array{cart:array<string,mixed>,products:array<int,array<string,mixed>>,errors:array<int,array<string,mixed>>}
     */
    private function emptyCart(): array
    {
        return [
            'cart' => [
                'items_count' => 0,
                'items_qty' => 0.0,
                'currency' => null,
                'items' => [],
            ],
            'products' => [],
            'errors' => [],
        ];
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid cart arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
