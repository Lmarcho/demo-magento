<?php
/**
 * Lmarcho RagSync Module - Observer Integration Tests
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Integration;

use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\ResourceModel\Queue\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea adminhtml
 */
class ObserverTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $queueCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ProductFactory
     */
    private ProductFactory $productFactory;

    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->queueCollectionFactory = $this->objectManager->get(CollectionFactory::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
        $this->pageRepository = $this->objectManager->get(PageRepositoryInterface::class);
        $this->pageFactory = $this->objectManager->get(PageFactory::class);
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 1
     */
    public function testProductSaveTriggersQueueEntry(): void
    {
        $product = $this->productFactory->create();
        $product->setTypeId('simple')
            ->setAttributeSetId(4) // Default attribute set
            ->setName('Test RAG Sync Product')
            ->setSku('test-ragsync-product-' . time())
            ->setPrice(99.99)
            ->setStatus(1)
            ->setVisibility(4)
            ->setStockData(['qty' => 100, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'product')
            ->addFieldToFilter('entity_id', $savedProduct->getId());

        $this->assertEquals(1, $collection->getSize());

        $queueItem = $collection->getFirstItem();
        $this->assertEquals(Queue::STATUS_PENDING, $queueItem->getStatus());
        $this->assertEquals(Queue::ACTION_SAVE, $queueItem->getAction());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 0
     */
    public function testProductSaveDoesNotQueueWhenDisabled(): void
    {
        $product = $this->productFactory->create();
        $product->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setName('Test RAG Sync Product Disabled')
            ->setSku('test-ragsync-disabled-' . time())
            ->setPrice(49.99)
            ->setStatus(1)
            ->setVisibility(4)
            ->setStockData(['qty' => 50, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'product')
            ->addFieldToFilter('entity_id', $savedProduct->getId());

        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/cms_pages/enabled 1
     * @magentoConfigFixture default/rag_sync/cms_pages/sync_mode whitelist
     * @magentoConfigFixture default/rag_sync/cms_pages/identifiers privacy-policy,returns,faq,test-page
     */
    public function testCmsPageSaveTriggersQueueEntryForWhitelistedPage(): void
    {
        $page = $this->pageFactory->create();
        $page->setIdentifier('test-page')
            ->setTitle('Test Page')
            ->setContent('<p>Test content</p>')
            ->setIsActive(true)
            ->setStores([0]);

        $savedPage = $this->pageRepository->save($page);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'cms_page')
            ->addFieldToFilter('entity_id', $savedPage->getId());

        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/cms_pages/enabled 1
     * @magentoConfigFixture default/rag_sync/cms_pages/sync_mode whitelist
     * @magentoConfigFixture default/rag_sync/cms_pages/identifiers privacy-policy,returns
     */
    public function testCmsPageSaveDoesNotQueueNonWhitelistedPage(): void
    {
        $page = $this->pageFactory->create();
        $page->setIdentifier('random-page-' . time())
            ->setTitle('Random Page')
            ->setContent('<p>Random content</p>')
            ->setIsActive(true)
            ->setStores([0]);

        $savedPage = $this->pageRepository->save($page);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'cms_page')
            ->addFieldToFilter('entity_id', $savedPage->getId());

        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testProductDeleteTriggersDeleteQueueEntry(): void
    {
        $product = $this->productRepository->get('simple');
        $productId = $product->getId();

        $this->productRepository->delete($product);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'product')
            ->addFieldToFilter('entity_id', $productId)
            ->addFieldToFilter('action', 'delete');

        $this->assertEquals(1, $collection->getSize());

        $queueItem = $collection->getFirstItem();
        $this->assertEquals(1, $queueItem->getPriority()); // Delete has highest priority
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 1
     */
    public function testMultipleProductSavesDeduplicateInQueue(): void
    {
        $product = $this->productFactory->create();
        $product->setTypeId('simple')
            ->setAttributeSetId(4)
            ->setName('Test Dedup Product')
            ->setSku('test-dedup-' . time())
            ->setPrice(29.99)
            ->setStatus(1)
            ->setVisibility(4)
            ->setStockData(['qty' => 10, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);

        // Update the product multiple times
        $savedProduct->setName('Test Dedup Product - Updated');
        $this->productRepository->save($savedProduct);

        $savedProduct->setName('Test Dedup Product - Updated Again');
        $this->productRepository->save($savedProduct);

        $collection = $this->queueCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', 'product')
            ->addFieldToFilter('entity_id', $savedProduct->getId());

        // Should only have one queue entry due to deduplication
        $this->assertEquals(1, $collection->getSize());
    }
}
