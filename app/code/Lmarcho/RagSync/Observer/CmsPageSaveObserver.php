<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Cms\Model\Page;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\CmsPageBuilder;

class CmsPageSaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CmsPageBuilder
     */
    private CmsPageBuilder $cmsPageBuilder;

    /**
     * @param QueueService $queueService
     * @param CmsPageBuilder $cmsPageBuilder
     */
    public function __construct(
        QueueService $queueService,
        CmsPageBuilder $cmsPageBuilder
    ) {
        $this->queueService = $queueService;
        $this->cmsPageBuilder = $cmsPageBuilder;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Page $page */
        $page = $observer->getEvent()->getObject();

        if (!$page || !$page->getId()) {
            return;
        }

        // Check if page should be synced based on config
        if (!$this->cmsPageBuilder->shouldSync($page)) {
            return;
        }

        // Get store IDs for the page
        $storeIds = $page->getStoreId();
        if (!is_array($storeIds)) {
            $storeIds = [$storeIds];
        }

        // If assigned to "All Store Views" (store 0), queue once with store 0
        if (in_array(0, $storeIds, false)) {
            $this->queueService->queueCmsPage((int)$page->getId(), 0);
            return;
        }

        // Queue for each assigned store to capture store-specific content
        foreach ($storeIds as $storeId) {
            $this->queueService->queueCmsPage((int)$page->getId(), (int)$storeId);
        }
    }
}
