<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Lmarcho\RagSync\Model\Config;

class ProductBuilder
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Configurable
     */
    private Configurable $configurableType;

    /**
     * @var ConfigurableResource
     */
    private ConfigurableResource $configurableResource;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Configurable $configurableType
     * @param ConfigurableResource $configurableResource
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        Configurable $configurableType,
        ConfigurableResource $configurableResource
    ) {
        $this->productRepository = $productRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->configurableType = $configurableType;
        $this->configurableResource = $configurableResource;
    }

    /**
     * Build product data for sync
     *
     * @param int $productId
     * @param int $storeId
     * @return array|null
     */
    public function build(int $productId, int $storeId = 0): ?array
    {
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromProduct($product, $storeId);
    }

    /**
     * Build product data from product object
     *
     * @param ProductInterface|Product $product
     * @param int $storeId
     * @return array
     */
    public function buildFromProduct(ProductInterface $product, int $storeId = 0): array
    {
        $data = [
            'id' => (int)$product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => $product->getTypeId(),
            'url_key' => $product->getUrlKey(),
            'description' => $this->cleanHtml($product->getDescription()),
            'short_description' => $this->cleanHtml($product->getShortDescription()),
            'meta_title' => $product->getMetaTitle(),
            'meta_description' => $product->getMetaDescription(),
            'meta_keywords' => $product->getMetaKeyword(),
            'status' => (int)$product->getStatus(),
            'visibility' => (int)$product->getVisibility(),
            'categories' => $this->getCategoryNames($product, $storeId),
            'category_ids' => array_map('intval', $product->getCategoryIds() ?: []),
            'attributes' => $this->getCustomAttributes($product, $storeId),
            'image_alt_texts' => $this->getImageAltTexts($product),
            'store_id' => $storeId,
        ];

        // Include configurable options for configurable products
        if ($product->getTypeId() === 'configurable') {
            $data['configurable_options'] = $this->getConfigurableOptions($product);
        }

        // Add URL if available
        if ($product instanceof Product) {
            try {
                $data['url'] = $product->getProductUrl();
            } catch (\Exception $e) {
                $data['url'] = null;
            }
        }

        return $data;
    }

    /**
     * Get category names for product
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array
     */
    private function getCategoryNames(ProductInterface $product, int $storeId = 0): array
    {
        $categoryIds = $product->getCategoryIds();

        if (empty($categoryIds)) {
            return [];
        }

        $excludedIds = $this->config->getExcludedCategoryIds($storeId);
        $categoryIds = array_diff($categoryIds, $excludedIds);

        if (empty($categoryIds)) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $categoryIds])
            ->addFieldToFilter('level', ['gteq' => 2]); // Skip root categories

        $names = [];
        foreach ($collection as $category) {
            $names[] = $category->getName();
        }

        return $names;
    }

    /**
     * Get custom attributes for product
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array
     */
    private function getCustomAttributes(ProductInterface $product, int $storeId): array
    {
        $attributeCodes = $this->config->getProductSyncAttributes($storeId);
        $attributes = [];

        foreach ($attributeCodes as $code) {
            $value = $this->getAttributeValue($product, $code);
            if ($value !== null && $value !== '') {
                $attributes[$code] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Get attribute value with label for select/multiselect
     *
     * @param ProductInterface|Product $product
     * @param string $attributeCode
     * @return mixed
     */
    private function getAttributeValue(ProductInterface $product, string $attributeCode)
    {
        if (!$product instanceof Product) {
            return $product->getData($attributeCode);
        }

        $attribute = $product->getResource()->getAttribute($attributeCode);

        if (!$attribute) {
            return null;
        }

        $value = $product->getData($attributeCode);

        if ($value === null || $value === '') {
            return null;
        }

        // Get label for select/multiselect attributes
        $frontendInput = $attribute->getFrontendInput();
        if (in_array($frontendInput, ['select', 'multiselect'])) {
            $optionText = $product->getAttributeText($attributeCode);
            if ($optionText) {
                return is_array($optionText) ? implode(', ', $optionText) : $optionText;
            }
        }

        return $value;
    }

    /**
     * Get image alt texts
     *
     * @param ProductInterface|Product $product
     * @return array
     */
    private function getImageAltTexts(ProductInterface $product): array
    {
        if (!$product instanceof Product) {
            return [];
        }

        $altTexts = [];
        $mediaGallery = $product->getMediaGalleryImages();

        if ($mediaGallery) {
            foreach ($mediaGallery as $image) {
                $label = $image->getLabel();
                if (!empty($label)) {
                    $altTexts[] = $label;
                }
            }
        }

        return array_unique($altTexts);
    }

    /**
     * Get configurable product options (colors, sizes, etc.)
     *
     * @param ProductInterface|Product $product
     * @return array
     */
    private function getConfigurableOptions(ProductInterface $product): array
    {
        if (!$product instanceof Product || $product->getTypeId() !== 'configurable') {
            return [];
        }

        $options = [];

        try {
            $configurableAttributes = $this->configurableType->getConfigurableAttributes($product);

            foreach ($configurableAttributes as $attribute) {
                $productAttribute = $attribute->getProductAttribute();
                if (!$productAttribute) {
                    continue;
                }

                $attributeCode = $productAttribute->getAttributeCode();
                $label = $productAttribute->getStoreLabel() ?: $productAttribute->getFrontendLabel();

                // Get all option values
                $optionValues = [];
                $attributeOptions = $productAttribute->getSource()->getAllOptions(false);

                // Get used values from children
                $usedOptions = $attribute->getOptions() ?: [];
                $usedValueIds = array_column($usedOptions, 'value_index');

                foreach ($attributeOptions as $option) {
                    if (in_array($option['value'], $usedValueIds)) {
                        $optionValues[] = $option['label'];
                    }
                }

                if (!empty($optionValues)) {
                    $options[] = [
                        'attribute_code' => $attributeCode,
                        'label' => $label,
                        'values' => $optionValues,
                    ];
                }
            }

            // Get variant count
            $childIds = $this->configurableResource->getChildrenIds($product->getId());
            $variantCount = count($childIds[0] ?? []);

            return [
                'options' => $options,
                'variant_count' => $variantCount,
                'options_summary' => $this->buildOptionsSummary($options),
            ];

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build human-readable options summary
     *
     * @param array $options
     * @return string
     */
    private function buildOptionsSummary(array $options): string
    {
        if (empty($options)) {
            return '';
        }

        $parts = [];
        foreach ($options as $option) {
            $label = $option['label'] ?? 'Option';
            $values = $option['values'] ?? [];
            if (!empty($values)) {
                $parts[] = "{$label}: " . implode(', ', $values);
            }
        }

        return !empty($parts) ? "Available options: " . implode('; ', $parts) : '';
    }

    /**
     * Clean HTML from content
     *
     * @param string|null $html
     * @return string|null
     */
    private function cleanHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        // Remove HTML tags but keep text
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text ?: null;
    }

    /**
     * Check if product should be synced based on config
     *
     * @param ProductInterface|Product $product
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSync(ProductInterface $product, ?int $storeId = null): bool
    {
        if (!$this->config->isProductSyncEnabled($storeId)) {
            return false;
        }

        // Check if disabled products should be included
        if (!$this->config->includeDisabledProducts($storeId)) {
            if ($product->getStatus() != \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
                return false;
            }
        }

        // Check if not visible products should be included
        if (!$this->config->includeNotVisibleProducts($storeId)) {
            $visibility = (int)$product->getVisibility();
            if ($visibility == \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE) {
                return false;
            }
        }

        // Check if product is in excluded categories
        $excludedCategoryIds = $this->config->getExcludedCategoryIds($storeId);
        if (!empty($excludedCategoryIds)) {
            $productCategoryIds = $product->getCategoryIds() ?: [];
            // If ALL product categories are excluded, don't sync
            $nonExcludedCategories = array_diff($productCategoryIds, $excludedCategoryIds);
            if (empty($nonExcludedCategories) && !empty($productCategoryIds)) {
                return false;
            }
        }

        return true;
    }
}
