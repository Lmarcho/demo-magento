<?php
/**
 * Lmarcho RagSync Module - ProductBuilder Unit Tests
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Unit\Model\DataBuilder;

use Lmarcho\RagSync\Model\DataBuilder\ProductBuilder;
use Lmarcho\RagSync\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProductBuilderTest extends TestCase
{
    /**
     * @var ProductBuilder
     */
    private ProductBuilder $productBuilder;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private $productRepositoryMock;

    /**
     * @var CategoryCollectionFactory|MockObject
     */
    private $categoryCollectionFactoryMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManagerMock;

    /**
     * @var Config|MockObject
     */
    private $configMock;

    protected function setUp(): void
    {
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryCollectionFactoryMock = $this->createMock(CategoryCollectionFactory::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->configMock = $this->createMock(Config::class);

        // Default config mocks
        $this->configMock->method('getExcludedCategoryIds')->willReturn([]);
        $this->configMock->method('getProductSyncAttributes')->willReturn([]);
        $this->configMock->method('isProductSyncEnabled')->willReturn(true);
        $this->configMock->method('includeDisabledProducts')->willReturn(false);
        $this->configMock->method('includeNotVisibleProducts')->willReturn(false);

        $this->productBuilder = new ProductBuilder(
            $this->productRepositoryMock,
            $this->categoryCollectionFactoryMock,
            $this->storeManagerMock,
            $this->configMock
        );
    }

    public function testBuildReturnsNullWhenProductNotFound(): void
    {
        $this->productRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(123, false, 1)
            ->willThrowException(new NoSuchEntityException());

        $result = $this->productBuilder->build(123, 1);

        $this->assertNull($result);
    }

    public function testBuildFromProductReturnsCorrectStructure(): void
    {
        $productMock = $this->createProductMock();
        $this->setupCategoryCollection([]);

        $result = $this->productBuilder->buildFromProduct($productMock, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('short_description', $result);
        $this->assertArrayHasKey('url_key', $result);
        $this->assertArrayHasKey('meta_title', $result);
        $this->assertArrayHasKey('meta_description', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('store_id', $result);
    }

    public function testBuildFromProductExtractsData(): void
    {
        $productMock = $this->createProductMock([
            'id' => 123,
            'sku' => 'TEST-SKU-123',
            'name' => 'Test Product Name',
            'description' => '<p>Full product description</p>',
            'short_description' => 'Short description',
            'url_key' => 'test-product',
            'meta_title' => 'Test Product | Store',
            'meta_description' => 'Meta description for SEO',
        ]);

        $this->setupCategoryCollection([]);

        $result = $this->productBuilder->buildFromProduct($productMock, 1);

        $this->assertEquals(123, $result['id']);
        $this->assertEquals('TEST-SKU-123', $result['sku']);
        $this->assertEquals('Test Product Name', $result['name']);
        $this->assertEquals('test-product', $result['url_key']);
        $this->assertEquals('Test Product | Store', $result['meta_title']);
        $this->assertEquals('Meta description for SEO', $result['meta_description']);
        $this->assertEquals(1, $result['store_id']);
    }

    public function testBuildFromProductStripsHtmlFromDescription(): void
    {
        $productMock = $this->createProductMock([
            'description' => '<p>This is a <strong>bold</strong> description.</p>',
        ]);

        $this->setupCategoryCollection([]);

        $result = $this->productBuilder->buildFromProduct($productMock, 1);

        $this->assertStringNotContainsString('<p>', $result['description']);
        $this->assertStringNotContainsString('<strong>', $result['description']);
        $this->assertStringContainsString('bold', $result['description']);
    }

    public function testShouldSyncReturnsTrueForEnabledProduct(): void
    {
        $productMock = $this->createProductMock([
            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'visibility' => \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
        ]);

        $result = $this->productBuilder->shouldSync($productMock, 1);

        $this->assertTrue($result);
    }

    public function testShouldSyncReturnsFalseWhenSyncDisabled(): void
    {
        $this->configMock = $this->createMock(Config::class);
        $this->configMock->method('isProductSyncEnabled')->willReturn(false);

        $this->productBuilder = new ProductBuilder(
            $this->productRepositoryMock,
            $this->categoryCollectionFactoryMock,
            $this->storeManagerMock,
            $this->configMock
        );

        $productMock = $this->createProductMock();

        $result = $this->productBuilder->shouldSync($productMock, 1);

        $this->assertFalse($result);
    }

    /**
     * Create a mock product with default or custom data
     *
     * @param array $data
     * @return Product|MockObject
     */
    private function createProductMock(array $data = []): MockObject
    {
        $defaults = [
            'id' => 1,
            'sku' => 'DEFAULT-SKU',
            'name' => 'Default Product',
            'type_id' => 'simple',
            'description' => 'Default description',
            'short_description' => 'Default short description',
            'url_key' => 'default-product',
            'meta_title' => 'Default Meta Title',
            'meta_description' => 'Default meta description',
            'meta_keyword' => 'keyword1,keyword2',
            'status' => 1,
            'visibility' => 4,
            'category_ids' => [],
        ];

        $data = array_merge($defaults, $data);

        // Use getMockBuilder to add magic methods that Magento Product uses
        $productMock = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->addMethods([
                'getDescription',
                'getShortDescription',
                'getUrlKey',
                'getMetaTitle',
                'getMetaDescription',
                'getMetaKeyword',
            ])
            ->onlyMethods([
                'getId',
                'getSku',
                'getName',
                'getTypeId',
                'getStatus',
                'getVisibility',
                'getCategoryIds',
                'getMediaGalleryImages',
                'getProductUrl',
            ])
            ->getMock();

        $productMock->method('getId')->willReturn($data['id']);
        $productMock->method('getSku')->willReturn($data['sku']);
        $productMock->method('getName')->willReturn($data['name']);
        $productMock->method('getTypeId')->willReturn($data['type_id']);
        $productMock->method('getDescription')->willReturn($data['description']);
        $productMock->method('getShortDescription')->willReturn($data['short_description']);
        $productMock->method('getUrlKey')->willReturn($data['url_key']);
        $productMock->method('getMetaTitle')->willReturn($data['meta_title']);
        $productMock->method('getMetaDescription')->willReturn($data['meta_description']);
        $productMock->method('getMetaKeyword')->willReturn($data['meta_keyword']);
        $productMock->method('getStatus')->willReturn($data['status']);
        $productMock->method('getVisibility')->willReturn($data['visibility']);
        $productMock->method('getCategoryIds')->willReturn($data['category_ids']);
        $productMock->method('getMediaGalleryImages')->willReturn(null);
        $productMock->method('getProductUrl')->willReturn('https://example.com/' . $data['url_key'] . '.html');

        return $productMock;
    }

    /**
     * Setup category collection mock
     *
     * @param array $categories
     */
    private function setupCategoryCollection(array $categories): void
    {
        $collectionMock = $this->createMock(CategoryCollection::class);
        $collectionMock->method('addAttributeToSelect')->willReturnSelf();
        $collectionMock->method('addFieldToFilter')->willReturnSelf();
        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->categoryCollectionFactoryMock->method('create')->willReturn($collectionMock);
    }
}
