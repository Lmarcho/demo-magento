<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Psr\Log\LoggerInterface;

class QueueService
{
    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param QueueResource $queueResource
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        QueueResource $queueResource,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->queueResource = $queueResource;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Add entity to sync queue
     *
     * @param string $entityType
     * @param int|string $entityId
     * @param int $storeId
     * @param string $action
     * @return int|null Queue item ID or null if not queued
     */
    public function addToQueue(
        string $entityType,
        $entityId,
        int $storeId = 0,
        string $action = Queue::ACTION_SAVE
    ): ?int {
        // Check if module is enabled
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }

        try {
            $queueId = $this->queueResource->addToQueue(
                $entityType,
                (string)$entityId,
                $storeId,
                $action
            );

            if ($this->config->isDebugEnabled()) {
                $this->logger->debug('RagSync: Added to queue', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'store_id' => $storeId,
                    'action' => $action,
                    'queue_id' => $queueId,
                ]);
            }

            return $queueId;
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Failed to add to queue', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Queue product for sync
     *
     * @param int $productId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueProduct(int $productId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isProductSyncEnabled($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_PRODUCT, $productId, $storeId, $action);
    }

    /**
     * Queue CMS page for sync
     *
     * @param int $pageId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueCmsPage(int $pageId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isCmsPageSyncEnabled($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_CMS_PAGE, $pageId, $storeId, $action);
    }

    /**
     * Queue CMS block for sync
     *
     * @param int $blockId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueCmsBlock(int $blockId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isCmsBlockSyncEnabled($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_CMS_BLOCK, $blockId, $storeId, $action);
    }

    /**
     * Queue category for sync
     *
     * @param int $categoryId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueCategory(int $categoryId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isCategorySyncEnabled($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_CATEGORY, $categoryId, $storeId, $action);
    }

    /**
     * Queue cart rule (promotion) for sync
     *
     * @param int $ruleId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueCartRule(int $ruleId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isPromotionSyncEnabled($storeId) || !$this->config->shouldSyncCartRules($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_PROMOTION, $ruleId, $storeId, $action);
    }

    /**
     * Queue catalog rule for sync
     *
     * @param int $ruleId
     * @param int $storeId
     * @param string $action
     * @return int|null
     */
    public function queueCatalogRule(int $ruleId, int $storeId = 0, string $action = Queue::ACTION_SAVE): ?int
    {
        if (!$this->config->isPromotionSyncEnabled($storeId) || !$this->config->shouldSyncCatalogRules($storeId)) {
            return null;
        }

        return $this->addToQueue(Queue::ENTITY_TYPE_CATALOG_RULE, $ruleId, $storeId, $action);
    }

    /**
     * Queue store config for sync
     *
     * @param int $storeId
     * @return int|null
     */
    public function queueStoreConfig(int $storeId): ?int
    {
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }

        // Use store_id as the entity_id for store config
        return $this->addToQueue(Queue::ENTITY_TYPE_STORE_CONFIG, $storeId, $storeId, Queue::ACTION_SAVE);
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->queueResource->getStatistics();
    }

    /**
     * Get oldest pending item age
     *
     * @return int|null
     */
    public function getOldestPendingAgeMinutes(): ?int
    {
        return $this->queueResource->getOldestPendingAgeMinutes();
    }
}
