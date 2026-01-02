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
use Lmarcho\RagSync\Model\Queue;

class CmsPageDeleteObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @param QueueService $queueService
     */
    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
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

        $this->queueService->queueCmsPage((int)$page->getId(), 0, Queue::ACTION_DELETE);
    }
}
