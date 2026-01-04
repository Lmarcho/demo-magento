<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Cms\Model\Block;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\CmsBlockBuilder;

class CmsBlockSaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CmsBlockBuilder
     */
    private CmsBlockBuilder $cmsBlockBuilder;

    /**
     * @param QueueService $queueService
     * @param CmsBlockBuilder $cmsBlockBuilder
     */
    public function __construct(
        QueueService $queueService,
        CmsBlockBuilder $cmsBlockBuilder
    ) {
        $this->queueService = $queueService;
        $this->cmsBlockBuilder = $cmsBlockBuilder;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Block $block */
        $block = $observer->getEvent()->getObject();

        if (!$block || !$block->getId()) {
            return;
        }

        // Check if block should be synced based on config
        if (!$this->cmsBlockBuilder->shouldSync($block)) {
            return;
        }

        // Get store IDs for the block
        $storeIds = $block->getStoreId();
        if (!is_array($storeIds)) {
            $storeIds = [$storeIds];
        }

        // If assigned to "All Store Views" (store 0), queue once with store 0
        if (in_array(0, $storeIds, false)) {
            $this->queueService->queueCmsBlock((int)$block->getId(), 0);
            return;
        }

        // Queue for each assigned store to capture store-specific content
        foreach ($storeIds as $storeId) {
            $this->queueService->queueCmsBlock((int)$block->getId(), (int)$storeId);
        }
    }
}
