<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Lmarcho\RagSync\Model\WebhookSender;
use Lmarcho\RagSync\Model\DataBuilder\ProductBuilder;
use Lmarcho\RagSync\Model\DataBuilder\CmsPageBuilder;
use Lmarcho\RagSync\Model\DataBuilder\CmsBlockBuilder;
use Lmarcho\RagSync\Model\DataBuilder\CategoryBuilder;
use Lmarcho\RagSync\Model\DataBuilder\PromotionBuilder;
use Lmarcho\RagSync\Model\DataBuilder\StoreConfigBuilder;
use Psr\Log\LoggerInterface;

class ProcessQueue
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var WebhookSender
     */
    private WebhookSender $webhookSender;

    /**
     * @var ProductBuilder
     */
    private ProductBuilder $productBuilder;

    /**
     * @var CmsPageBuilder
     */
    private CmsPageBuilder $cmsPageBuilder;

    /**
     * @var CmsBlockBuilder
     */
    private CmsBlockBuilder $cmsBlockBuilder;

    /**
     * @var CategoryBuilder
     */
    private CategoryBuilder $categoryBuilder;

    /**
     * @var PromotionBuilder
     */
    private PromotionBuilder $promotionBuilder;

    /**
     * @var StoreConfigBuilder
     */
    private StoreConfigBuilder $storeConfigBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param QueueResource $queueResource
     * @param WebhookSender $webhookSender
     * @param ProductBuilder $productBuilder
     * @param CmsPageBuilder $cmsPageBuilder
     * @param CmsBlockBuilder $cmsBlockBuilder
     * @param CategoryBuilder $categoryBuilder
     * @param PromotionBuilder $promotionBuilder
     * @param StoreConfigBuilder $storeConfigBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        QueueResource $queueResource,
        WebhookSender $webhookSender,
        ProductBuilder $productBuilder,
        CmsPageBuilder $cmsPageBuilder,
        CmsBlockBuilder $cmsBlockBuilder,
        CategoryBuilder $categoryBuilder,
        PromotionBuilder $promotionBuilder,
        StoreConfigBuilder $storeConfigBuilder,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->queueResource = $queueResource;
        $this->webhookSender = $webhookSender;
        $this->productBuilder = $productBuilder;
        $this->cmsPageBuilder = $cmsPageBuilder;
        $this->cmsBlockBuilder = $cmsBlockBuilder;
        $this->categoryBuilder = $categoryBuilder;
        $this->promotionBuilder = $promotionBuilder;
        $this->storeConfigBuilder = $storeConfigBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute cron job - process pending queue items
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if (!$this->config->isConnectionConfigured()) {
            $this->logger->warning('RagSync: Connection not configured, skipping queue processing');
            return;
        }

        $batchSize = $this->config->getQueueBatchSize();
        $maxRetries = $this->config->getMaxRetries();

        // Get pending items
        $pendingItems = $this->queueResource->getPendingItems($batchSize);

        // Also get items ready for retry
        $retryDelays = $this->config->getRetryDelays();
        $retryItems = $this->queueResource->getItemsForRetry($retryDelays, $batchSize - count($pendingItems));

        $candidates = array_merge($pendingItems, $retryItems);

        if (empty($candidates)) {
            return;
        }

        // Atomically claim the candidates so a concurrent run (cron + admin button
        // + CLI all call this execute()) cannot pick up and send the same items.
        $token = bin2hex(random_bytes(16));
        if ($this->queueResource->claimItems(array_column($candidates, 'id'), $token) === 0) {
            return;
        }
        $items = $this->queueResource->getClaimedItems($token);
        if (empty($items)) {
            return;
        }

        // Build batch payload
        $batchItems = [];
        $batchIds = [];
        $skippedIds = [];

        foreach ($items as $item) {
            $data = $this->buildEntityData($item);

            if ($data === null) {
                // Entity not found or should not be synced - nothing to send.
                $skippedIds[] = $item['id'];
                continue;
            }

            $batchItems[] = [
                'type' => $item['entity_type'],
                'id' => $item['entity_id'],
                'action' => $item['action'],
                'store_id' => (int)$item['store_id'],
                'data' => $data,
            ];
            $batchIds[] = $item['id'];
        }

        // Skipped items are not failures; resolve them independently of the batch.
        if (!empty($skippedIds)) {
            $this->queueResource->markAsSent($skippedIds);
        }

        if (empty($batchItems)) {
            return;
        }

        // Send batch to webhook
        $response = $this->webhookSender->sendBatch($batchItems);

        if ($response->isSuccess()) {
            $this->queueResource->markAsSent($batchIds);

            $this->logger->info('RagSync: Batch processed successfully', [
                'count' => count($batchItems),
                'duration_ms' => $response->getDurationMs(),
            ]);
        } else {
            // Handle failure
            $errorMessage = $response->getErrorMessage();

            if ($response->isPermanentError()) {
                // Permanent error - mark as dead immediately (0 retries)
                $this->queueResource->markAsFailed($batchIds, $errorMessage, 0);

                $this->logger->error('RagSync: Batch failed with permanent error', [
                    'error' => $errorMessage,
                    'count' => count($batchItems),
                ]);
            } else {
                // Transient error - mark for retry
                $this->queueResource->markAsFailed($batchIds, $errorMessage, $maxRetries);

                $this->logger->warning('RagSync: Batch failed, will retry', [
                    'error' => $errorMessage,
                    'count' => count($batchItems),
                ]);
            }
        }
    }

    /**
     * Build entity data for webhook payload
     *
     * @param array $item
     * @return array|null
     */
    private function buildEntityData(array $item): ?array
    {
        $entityType = $item['entity_type'];
        $entityId = (int)$item['entity_id'];
        $storeId = (int)$item['store_id'];
        $action = $item['action'];

        // For delete actions, just return identifier data
        if ($action === Queue::ACTION_DELETE) {
            return [
                'id' => $entityId,
                'entity_type' => $entityType,
                'store_id' => $storeId,
            ];
        }

        // Build full entity data based on type
        switch ($entityType) {
            case Queue::ENTITY_TYPE_PRODUCT:
                return $this->productBuilder->build($entityId, $storeId);

            case Queue::ENTITY_TYPE_CMS_PAGE:
                return $this->cmsPageBuilder->build($entityId);

            case Queue::ENTITY_TYPE_CMS_BLOCK:
                return $this->cmsBlockBuilder->build($entityId);

            case Queue::ENTITY_TYPE_CATEGORY:
                return $this->categoryBuilder->build($entityId, $storeId);

            case Queue::ENTITY_TYPE_PROMOTION:
                return $this->promotionBuilder->buildCartRule($entityId);

            case Queue::ENTITY_TYPE_CATALOG_RULE:
                return $this->promotionBuilder->buildCatalogRule($entityId);

            case Queue::ENTITY_TYPE_STORE_CONFIG:
                return $this->storeConfigBuilder->build($storeId);

            default:
                $this->logger->warning('RagSync: Unknown entity type', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ]);
                return null;
        }
    }
}
