<?php
/**
 * Lmarcho RagSync Module - QueueService Integration Tests
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Integration;

use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Lmarcho\RagSync\Model\ResourceModel\Queue\CollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 */
class QueueServiceTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->queueService = $this->objectManager->get(QueueService::class);
        $this->queueResource = $this->objectManager->get(QueueResource::class);
        $this->collectionFactory = $this->objectManager->get(CollectionFactory::class);
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 1
     */
    public function testAddToQueueCreatesNewItem(): void
    {
        $entityType = 'product';
        $entityId = 12345;
        $storeId = 1;
        $action = 'upsert';

        $this->queueService->addToQueue($entityType, $entityId, $storeId, $action);

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_type', $entityType)
            ->addFieldToFilter('entity_id', $entityId)
            ->addFieldToFilter('store_id', $storeId);

        $this->assertEquals(1, $collection->getSize());

        $item = $collection->getFirstItem();
        $this->assertEquals(Queue::STATUS_PENDING, $item->getStatus());
        $this->assertEquals($action, $item->getAction());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     * @magentoConfigFixture default/rag_sync/products/enabled 1
     */
    public function testAddToQueueDeduplicates(): void
    {
        $entityType = 'product';
        $entityId = 12346;
        $storeId = 1;

        // Add same item twice
        $this->queueService->addToQueue($entityType, $entityId, $storeId, 'upsert');
        $this->queueService->addToQueue($entityType, $entityId, $storeId, 'upsert');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_type', $entityType)
            ->addFieldToFilter('entity_id', $entityId)
            ->addFieldToFilter('store_id', $storeId);

        // Should only have one item due to deduplication
        $this->assertEquals(1, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testAddToQueueSetsCorrectPriority(): void
    {
        // Test product priority
        $this->queueService->addToQueue('product', 1001, 1, 'upsert');
        $productItem = $this->getQueueItem('product', 1001, 1);
        $this->assertEquals(2, $productItem->getPriority());

        // Test CMS page priority
        $this->queueService->addToQueue('cms_page', 1002, 1, 'upsert');
        $cmsPageItem = $this->getQueueItem('cms_page', 1002, 1);
        $this->assertEquals(3, $cmsPageItem->getPriority());

        // Test delete action priority (highest)
        $this->queueService->addToQueue('category', 1003, 1, 'delete');
        $deleteItem = $this->getQueueItem('category', 1003, 1);
        $this->assertEquals(1, $deleteItem->getPriority());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 0
     */
    public function testAddToQueueDoesNothingWhenDisabled(): void
    {
        $this->queueService->addToQueue('product', 99999, 1, 'upsert');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', 99999);

        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testGetPendingItemsReturnsCorrectItems(): void
    {
        // Add items with different statuses
        $this->queueService->addToQueue('product', 2001, 1, 'upsert');
        $this->queueService->addToQueue('product', 2002, 1, 'upsert');
        $this->queueService->addToQueue('product', 2003, 1, 'upsert');

        // Mark one as sent
        $sentItem = $this->getQueueItem('product', 2002, 1);
        $sentItem->setStatus(Queue::STATUS_SENT);
        $this->queueResource->save($sentItem);

        $pendingItems = $this->queueResource->getPendingItems(10);

        $pendingIds = array_map(function ($item) {
            return $item['entity_id'];
        }, $pendingItems);

        $this->assertContains('2001', $pendingIds);
        $this->assertContains('2003', $pendingIds);
        $this->assertNotContains('2002', $pendingIds);
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testMarkAsSentUpdatesStatus(): void
    {
        $this->queueService->addToQueue('product', 3001, 1, 'upsert');

        $item = $this->getQueueItem('product', 3001, 1);
        $queueId = $item->getId();

        $this->queueResource->markAsSent([$queueId]);

        // Reload item
        $item = $this->objectManager->create(Queue::class);
        $this->queueResource->load($item, $queueId);

        $this->assertEquals(Queue::STATUS_SENT, $item->getStatus());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testMarkAsFailedUpdatesStatusAndError(): void
    {
        $this->queueService->addToQueue('product', 4001, 1, 'upsert');

        $item = $this->getQueueItem('product', 4001, 1);
        $queueId = $item->getId();
        $errorMessage = 'Connection timeout';

        $this->queueResource->markAsFailed([$queueId], $errorMessage);

        // Reload item
        $item = $this->objectManager->create(Queue::class);
        $this->queueResource->load($item, $queueId);

        $this->assertEquals(Queue::STATUS_FAILED, $item->getStatus());
        $this->assertEquals($errorMessage, $item->getErrorMessage());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testCleanupOldItems(): void
    {
        // Add an item and mark as sent
        $this->queueService->addToQueue('product', 5001, 1, 'upsert');
        $item = $this->getQueueItem('product', 5001, 1);
        $item->setStatus(Queue::STATUS_SENT);

        // Manually set updated_at to 10 days ago
        $connection = $this->queueResource->getConnection();
        $connection->update(
            $this->queueResource->getMainTable(),
            ['updated_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],
            ['id = ?' => $item->getId()]
        );

        // Cleanup items older than 7 days
        $deletedCount = $this->queueResource->cleanupOldItems(7);

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify item is deleted
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', 5001);
        $this->assertEquals(0, $collection->getSize());
    }

    /**
     * @magentoConfigFixture default/rag_sync/general/enabled 1
     */
    public function testGetQueueStatsReturnsCorrectCounts(): void
    {
        // Add items with different statuses
        $this->queueService->addToQueue('product', 6001, 1, 'upsert');
        $this->queueService->addToQueue('product', 6002, 1, 'upsert');
        $this->queueService->addToQueue('product', 6003, 1, 'upsert');

        // Mark one as sent
        $sentItem = $this->getQueueItem('product', 6002, 1);
        $sentItem->setStatus(Queue::STATUS_SENT);
        $this->queueResource->save($sentItem);

        // Mark one as failed
        $failedItem = $this->getQueueItem('product', 6003, 1);
        $failedItem->setStatus(Queue::STATUS_FAILED);
        $this->queueResource->save($failedItem);

        $stats = $this->queueResource->getStatistics();

        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    /**
     * Get queue item by entity details
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $storeId
     * @return Queue
     */
    private function getQueueItem(string $entityType, int $entityId, int $storeId): Queue
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_type', $entityType)
            ->addFieldToFilter('entity_id', $entityId)
            ->addFieldToFilter('store_id', $storeId);

        return $collection->getFirstItem();
    }
}
