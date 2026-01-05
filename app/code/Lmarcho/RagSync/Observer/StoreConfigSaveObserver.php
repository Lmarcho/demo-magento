<?php
/**
 * Lmarcho RagSync Module - Store Config Save Observer
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\StoreConfigBuilder;
use Psr\Log\LoggerInterface;

class StoreConfigSaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var StoreConfigBuilder
     */
    private StoreConfigBuilder $storeConfigBuilder;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param QueueService $queueService
     * @param StoreConfigBuilder $storeConfigBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        QueueService $queueService,
        StoreConfigBuilder $storeConfigBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->queueService = $queueService;
        $this->storeConfigBuilder = $storeConfigBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Handle config section save event
     *
     * This observer fires when general, currency, or trans_email sections are saved.
     * It queues all affected stores for config sync.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            // Get scope info from request if available
            $request = $observer->getEvent()->getRequest();
            $website = $request ? $request->getParam('website', '') : '';
            $store = $request ? $request->getParam('store', '') : '';

            // Determine affected store IDs based on scope
            $storeIds = $this->getAffectedStoreIds($website, $store);

            foreach ($storeIds as $storeId) {
                if (!$this->storeConfigBuilder->shouldSync($storeId)) {
                    continue;
                }

                $this->queueService->queueStoreConfig($storeId);

                $this->logger->info('RagSync: Store config queued for sync', [
                    'store_id' => $storeId,
                    'section' => $observer->getEvent()->getName(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Failed to queue store config sync', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get store IDs affected by config change
     *
     * @param string $website
     * @param string $store
     * @return array
     */
    private function getAffectedStoreIds(string $website, string $store): array
    {
        try {
            // Store scope - single store
            if (!empty($store)) {
                $storeModel = $this->storeManager->getStore($store);
                return [(int)$storeModel->getId()];
            }

            // Website scope - all stores in website
            if (!empty($website)) {
                $websiteModel = $this->storeManager->getWebsite($website);
                $stores = $websiteModel->getStores();
                return array_map(
                    fn($s) => (int)$s->getId(),
                    $stores
                );
            }

            // Default scope - all stores
            $storeIds = [];
            foreach ($this->storeManager->getStores() as $storeModel) {
                $storeIds[] = (int)$storeModel->getId();
            }
            return $storeIds;
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Failed to get affected store IDs', [
                'website' => $website,
                'store' => $store,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
