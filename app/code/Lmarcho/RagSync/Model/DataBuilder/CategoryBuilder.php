<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Lmarcho\RagSync\Model\Config;

class CategoryBuilder
{
    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Config $config
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CategoryCollectionFactory $categoryCollectionFactory,
        Config $config
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->config = $config;
    }

    /**
     * Build category data for sync
     *
     * @param int $categoryId
     * @param int $storeId
     * @return array|null
     */
    public function build(int $categoryId, int $storeId = 0): ?array
    {
        try {
            $category = $this->categoryRepository->get($categoryId, $storeId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $this->buildFromCategory($category, $storeId);
    }

    /**
     * Build category data from category object
     *
     * @param CategoryInterface|Category $category
     * @param int $storeId
     * @return array
     */
    public function buildFromCategory(CategoryInterface $category, int $storeId = 0): array
    {
        $data = [
            'id' => (int)$category->getId(),
            'name' => $category->getName(),
            'description' => $this->cleanHtml($category->getDescription()),
            'url_key' => $category->getUrlKey(),
            'url_path' => $category->getUrlPath(),
            'level' => (int)$category->getLevel(),
            'position' => (int)$category->getPosition(),
            'is_active' => (bool)$category->getIsActive(),
            'include_in_menu' => (bool)$category->getIncludeInMenu(),
            'parent_id' => (int)$category->getParentId(),
            'path' => $category->getPath(),
            'full_path' => $this->getFullPath($category),
            'parent_names' => $this->getParentNames($category),
            'meta_title' => $category->getMetaTitle(),
            'meta_description' => $category->getMetaDescription(),
            'meta_keywords' => $category->getMetaKeywords(),
            'store_id' => $storeId,
            'document_type' => 'category',
        ];

        // Add product count if enabled
        if ($this->config->includeCategoryProductCount($storeId)) {
            $data['product_count'] = $this->getProductCount($category);
        }

        return $data;
    }

    /**
     * Get full category path as breadcrumb
     *
     * @param CategoryInterface|Category $category
     * @return string
     */
    private function getFullPath(CategoryInterface $category): string
    {
        $pathIds = explode('/', $category->getPath());

        // Remove root category (level 0) and store root category (level 1)
        // These are always the first 2 elements in the path regardless of their IDs
        $pathIds = array_slice($pathIds, 2);

        if (empty($pathIds)) {
            return $category->getName();
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $pathIds])
            ->addAttributeToSort('level', 'ASC');

        $names = [];
        foreach ($collection as $cat) {
            $names[] = $cat->getName();
        }

        return implode(' > ', $names);
    }

    /**
     * Get parent category names
     *
     * @param CategoryInterface|Category $category
     * @return array
     */
    private function getParentNames(CategoryInterface $category): array
    {
        $pathIds = explode('/', $category->getPath());

        // Remove root category (level 0), store root (level 1), and current category
        // First 2 elements are always root categories regardless of their IDs
        $pathIds = array_slice($pathIds, 2, -1);

        if (empty($pathIds)) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $pathIds])
            ->addAttributeToSort('level', 'ASC');

        $names = [];
        foreach ($collection as $cat) {
            $names[] = $cat->getName();
        }

        return $names;
    }

    /**
     * Get product count for category
     *
     * @param CategoryInterface|Category $category
     * @return int
     */
    private function getProductCount(CategoryInterface $category): int
    {
        if ($category instanceof Category) {
            return (int)$category->getProductCount();
        }

        return 0;
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

        // Remove widget directives
        $html = preg_replace('/\{\{[^}]+\}\}/', '', $html);

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
     * Check if category should be synced based on config
     *
     * @param CategoryInterface|Category $category
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSync(CategoryInterface $category, ?int $storeId = null): bool
    {
        if (!$this->config->isCategorySyncEnabled($storeId)) {
            return false;
        }

        // Check minimum level
        $minLevel = $this->config->getCategoryMinLevel($storeId);
        if ((int)$category->getLevel() < $minLevel) {
            return false;
        }

        // Check if inactive categories should be included
        if (!$this->config->includeInactiveCategories($storeId)) {
            if (!$category->getIsActive()) {
                return false;
            }
        }

        // Check if category is in excluded list
        $excludedIds = $this->config->getExcludedCategoryIds($storeId);
        if (in_array((int)$category->getId(), $excludedIds, true)) {
            return false;
        }

        return true;
    }
}
