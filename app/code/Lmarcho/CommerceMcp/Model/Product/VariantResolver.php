<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Product;

use Lmarcho\CommerceMcp\Api\Data\StoreContextInterface;
use Lmarcho\CommerceMcp\Api\ProductVariantResolverInterface;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class VariantResolver implements ProductVariantResolverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PriceResolver $priceResolver,
        private readonly VariantImageResolver $variantImageResolver,
        private readonly AvailabilityResolver $availabilityResolver
    ) {
    }

    public function resolve(
        Product $parent,
        StoreContextInterface $storeContext,
        ?int $requestedLimit = null
    ): array {
        if ($parent->getTypeId() !== Configurable::TYPE_CODE) {
            return [
                'options' => [],
                'variants' => [],
                'total' => 0,
                'returned' => 0,
                'truncated' => false,
            ];
        }

        $type = $parent->getTypeInstance();
        if (!$type instanceof Configurable) {
            return [
                'options' => [],
                'variants' => [],
                'total' => 0,
                'returned' => 0,
                'truncated' => false,
            ];
        }

        $configurableAttributes = array_values($type->getConfigurableAttributes($parent)->getItems());
        $attributes = array_map(
            static fn($attribute) => $attribute->getProductAttribute(),
            $configurableAttributes
        );
        foreach ($attributes as $attribute) {
            $attribute->setStoreId($storeContext->getStoreId());
        }
        $attributeCodes = array_map(
            static fn($attribute): string => (string)$attribute->getAttributeCode(),
            $attributes
        );
        $limit = min(
            max(1, $requestedLimit ?? $this->config->getMaxVariantsPerProduct()),
            $this->config->getMaxVariantsPerProduct()
        );

        $collection = $type->getUsedProductCollection($parent);
        $collection->setStoreId($storeContext->getStoreId())
            ->addAttributeToSelect(array_merge([
                'sku',
                'name',
                'status',
                'price',
                'special_price',
                'special_from_date',
                'special_to_date',
                'image',
                'image_label',
            ], $attributeCodes))
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addPriceData()
            ->setOrder('entity_id', 'ASC');

        $total = $collection->getSize();
        $collection->setPageSize($limit)->setCurPage(1);
        $children = array_values($collection->getItems());
        $skus = array_map(
            static fn(Product $product): string => (string)$product->getSku(),
            $children
        );
        $availability = $this->availabilityResolver->resolve(
            $skus,
            $storeContext->getStockId()
        );
        $variants = [];
        foreach ($children as $child) {
            $selections = [];
            foreach ($attributes as $attribute) {
                $code = (string)$attribute->getAttributeCode();
                $value = $child->getData($code);
                $label = $value === null ? null : $child->getAttributeText($code);
                $label = is_array($label) ? implode(', ', $label) : $label;
                $selection = [
                    'code' => $code,
                    'label' => (string)$attribute->getStoreLabel(),
                    'value' => $value === null ? null : (string)$value,
                    'value_label' => $label === false || $label === null ? null : (string)$label,
                ];
                $selections[] = $selection;
            }

            $image = $this->variantImageResolver->resolve($child, $parent);

            $variants[] = [
                'sku' => (string)$child->getSku(),
                'name' => (string)$child->getName(),
                'options' => $selections,
                'price' => $this->priceResolver->resolve(
                    $child,
                    $storeContext->getCurrency()
                ),
                'availability' => $availability[$child->getSku()]
                    ?? ['is_salable' => null, 'status' => 'UNKNOWN'],
                'primary_image' => $image['primary_image'],
                'image_fallback' => $image['image_fallback'],
            ];
        }

        $options = [];
        foreach ($configurableAttributes as $configurableAttribute) {
            $attribute = $configurableAttribute->getProductAttribute();
            $values = [];
            foreach ($configurableAttribute->getOptions() ?: [] as $option) {
                $value = $option['value_index'] ?? null;
                if ($value === null) {
                    continue;
                }
                $values[] = [
                    'value' => (string)$value,
                    'label' => (string)($option['store_label'] ?? $option['label'] ?? ''),
                ];
            }
            $options[] = [
                'code' => (string)$attribute->getAttributeCode(),
                'label' => (string)$configurableAttribute->getLabel(),
                'values' => $values,
            ];
        }

        return [
            'options' => $options,
            'variants' => $variants,
            'total' => (int)$total,
            'returned' => count($variants),
            'truncated' => $total > count($variants),
        ];
    }
}
