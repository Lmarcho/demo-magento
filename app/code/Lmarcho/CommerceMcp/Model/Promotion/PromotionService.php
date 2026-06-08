<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Promotion;

use Lmarcho\CommerceMcp\Api\PromotionServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Magento\Customer\Model\Group;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as SalesRuleCollectionFactory;
use Magento\SalesRule\Model\Rule as SalesRule;
use Magento\Store\Model\StoreManagerInterface;

class PromotionService implements PromotionServiceInterface
{
    private const PROMOTION_TYPES = ['catalog', 'cart'];

    public function __construct(
        private readonly Config $config,
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CatalogRuleCollectionFactory $catalogRuleCollectionFactory,
        private readonly SalesRuleCollectionFactory $salesRuleCollectionFactory
    ) {
    }

    public function getActive(
        string $storeCode,
        array $skus,
        array $promotionTypes,
        ?int $limit = null
    ): array {
        $skus = $this->normalizeSkus($skus);
        $promotionTypes = $this->normalizePromotionTypes($promotionTypes);
        $limit = min(
            max(1, $limit ?? $this->config->getMaxPromotions()),
            $this->config->getMaxPromotions()
        );

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);

        try {
            $products = $skus === [] ? [] : $this->loadProducts($skus, $context->getStoreId(), $store);
            $promotions = [];
            if (in_array('catalog', $promotionTypes, true)) {
                $promotions = array_merge(
                    $promotions,
                    $this->catalogPromotions($context->getWebsiteId(), $products)
                );
            }
            if (in_array('cart', $promotionTypes, true)) {
                $promotions = array_merge(
                    $promotions,
                    $this->cartPromotions($context->getWebsiteId(), $products)
                );
            }

            usort(
                $promotions,
                static fn(array $left, array $right): int => [
                    $left['starts_at'] ?? '',
                    $left['external_id'],
                ] <=> [
                    $right['starts_at'] ?? '',
                    $right['external_id'],
                ]
            );

            return [
                'store' => [
                    'store_code' => $context->getStoreCode(),
                    'timezone' => $context->getTimezone(),
                ],
                'promotions' => array_slice($promotions, 0, $limit),
                'total' => count($promotions),
                'returned' => min(count($promotions), $limit),
            ];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }

    /**
     * @param array<string,Product> $products
     * @return array<int,array<string,mixed>>
     */
    private function catalogPromotions(int $websiteId, array $products): array
    {
        $today = gmdate('Y-m-d');
        $collection = $this->catalogRuleCollectionFactory->create();
        $collection->addWebsiteFilter($websiteId)
            ->addCustomerGroupFilter(Group::NOT_LOGGED_IN_ID)
            ->addFieldToFilter('is_active', 1)
            ->setOrder('sort_order', 'ASC');
        $collection->getSelect()
            ->where('(main_table.from_date IS NULL OR main_table.from_date <= ?)', $today)
            ->where('(main_table.to_date IS NULL OR main_table.to_date >= ?)', $today);

        $promotions = [];
        foreach ($collection as $rule) {
            $applicableSkus = $this->matchingSkus($rule, $products, 'conditions');
            if ($products !== [] && $applicableSkus === []) {
                continue;
            }
            $promotions[] = [
                'external_id' => (string)$rule->getId(),
                'type' => 'catalog',
                'name' => (string)$rule->getName(),
                'public_label' => $this->publicLabel((string)$rule->getName()),
                'description' => $this->description(
                    (string)$rule->getDescription(),
                    'Discount is reflected in the displayed product price.'
                ),
                'starts_at' => $this->dateToUtc($rule->getFromDate(), false),
                'ends_at' => $this->dateToUtc($rule->getToDate(), true),
                'coupon_required' => false,
                'coupon_code' => null,
                'applicable_skus' => $applicableSkus,
                'eligibility' => 'POTENTIALLY_ELIGIBLE',
            ];
        }

        return $promotions;
    }

    /**
     * @param array<string,Product> $products
     * @return array<int,array<string,mixed>>
     */
    private function cartPromotions(int $websiteId, array $products): array
    {
        $publicCoupons = $this->config->getPublicCouponCodes();
        $collection = $this->salesRuleCollectionFactory->create();
        $collection->setValidationFilter(
            $websiteId,
            Group::NOT_LOGGED_IN_ID,
            '',
            gmdate('Y-m-d'),
            null,
            $publicCoupons
        )
            ->addAllowedSalesRulesFilter();

        $promotions = [];
        foreach ($collection as $rule) {
            $couponType = (int)$rule->getCouponType();
            $couponRequired = $couponType !== SalesRule::COUPON_TYPE_NO_COUPON;
            $couponCode = $this->publicCouponCode($rule, $publicCoupons);
            if ($couponRequired && $couponCode === null) {
                continue;
            }

            $applicableSkus = $this->matchingSkus($rule, $products, 'actions');
            if ($products !== [] && $applicableSkus === []) {
                $applicableSkus = array_keys($products);
            }

            $promotions[] = [
                'external_id' => (string)$rule->getId(),
                'type' => 'cart',
                'name' => (string)$rule->getName(),
                'public_label' => $this->publicLabel((string)$rule->getName()),
                'description' => $this->description(
                    (string)$rule->getDescription(),
                    'Cart rule is potentially eligible until a real cart is evaluated.'
                ),
                'starts_at' => $this->dateToUtc($rule->getFromDate(), false),
                'ends_at' => $this->dateToUtc($rule->getToDate(), true),
                'coupon_required' => $couponRequired,
                'coupon_code' => $couponCode,
                'applicable_skus' => $applicableSkus,
                'eligibility' => 'POTENTIALLY_ELIGIBLE',
            ];
        }

        return $promotions;
    }

    /**
     * @param array<string,Product> $products
     * @return string[]
     */
    private function matchingSkus(mixed $rule, array $products, string $conditionType): array
    {
        if ($products === []) {
            return [];
        }
        $validator = $conditionType === 'actions' ? $rule->getActions() : $rule->getConditions();
        $matched = [];
        foreach ($products as $sku => $product) {
            try {
                if ($validator->validate($product)) {
                    $matched[] = $sku;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $matched;
    }

    /**
     * @param string[] $skus
     * @return array<string,Product>
     */
    private function loadProducts(array $skus, int $storeId, mixed $store): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($store)
            ->addAttributeToSelect(['sku', 'name', 'status', 'visibility'])
            ->addAttributeToFilter('sku', ['in' => $skus])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH,
            ]]);

        $products = [];
        foreach ($collection as $product) {
            if ($product instanceof Product) {
                $products[(string)$product->getSku()] = $product;
            }
        }

        return $products;
    }

    private function publicCouponCode(SalesRule $rule, array $publicCoupons): ?string
    {
        if ((int)$rule->getCouponType() !== SalesRule::COUPON_TYPE_SPECIFIC) {
            return null;
        }
        $displayCode = trim((string)($rule->getData('code') ?: $rule->getCouponCode()));
        $code = strtoupper($displayCode);
        if ($code === '' || !in_array($code, $publicCoupons, true)) {
            return null;
        }

        return $displayCode;
    }

    private function publicLabel(string $name): string
    {
        return $name === '' ? 'Active promotion' : $name;
    }

    private function description(string $description, string $fallback): string
    {
        $description = trim(strip_tags($description));

        return $description === '' ? $fallback : $description;
    }

    private function dateToUtc(mixed $date, bool $endOfDay): ?string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return null;
        }
        $time = $endOfDay ? '23:59:59' : '00:00:00';

        return gmdate('c', strtotime($date . ' ' . $time . ' UTC') ?: 0);
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
        $normalized = array_values(array_unique($normalized));
        if (count($normalized) > $this->config->getMaxSkusPerRequest()) {
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

        return $normalized;
    }

    /**
     * @param mixed[] $promotionTypes
     * @return string[]
     */
    private function normalizePromotionTypes(array $promotionTypes): array
    {
        if ($promotionTypes === []) {
            return self::PROMOTION_TYPES;
        }
        foreach ($promotionTypes as $promotionType) {
            if (!is_string($promotionType) || !in_array($promotionType, self::PROMOTION_TYPES, true)) {
                throw new JsonRpcException(
                    'Invalid promotion type',
                    -32602,
                    null,
                    ['error_code' => 'INVALID_PROMOTION_TYPE']
                );
            }
        }

        return array_values(array_unique($promotionTypes));
    }
}
