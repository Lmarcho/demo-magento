<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\ProductVariantResolverInterface;
use Lmarcho\CommerceMcp\Api\ProductVariantServiceInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductVariantService implements ProductVariantServiceInterface
{
    public function __construct(
        private readonly StoreContextResolverInterface $storeContextResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductVariantResolverInterface $variantResolver
    ) {
    }

    public function get(string $storeCode, string $sku, ?int $limit = null): array
    {
        $sku = trim($sku);
        if ($sku === '' || strlen($sku) > 64) {
            throw new JsonRpcException(
                'Invalid product SKU',
                -32602,
                null,
                ['error_code' => 'INVALID_SKU']
            );
        }

        $context = $this->storeContextResolver->resolve($storeCode);
        $originalStoreId = (int)$this->storeManager->getStore()->getId();
        $store = $this->storeManager->getStore($context->getStoreId());
        $this->storeManager->setCurrentStore($store);

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
                    'image',
                    'image_label',
                ])
                ->addAttributeToFilter('sku', $sku)
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ]])
                ->setPageSize(1);
            $parent = $collection->getFirstItem();

            if (!$parent instanceof Product || !$parent->getId()) {
                throw new JsonRpcException(
                    'Product is not available',
                    -32602,
                    null,
                    ['error_code' => 'PRODUCT_NOT_AVAILABLE']
                );
            }

            return [
                'product' => [
                    'sku' => (string)$parent->getSku(),
                    'name' => (string)$parent->getName(),
                    'type' => (string)$parent->getTypeId(),
                ],
                ...$this->variantResolver->resolve($parent, $context, $limit),
            ];
        } finally {
            $this->storeManager->setCurrentStore($originalStoreId);
        }
    }
}
