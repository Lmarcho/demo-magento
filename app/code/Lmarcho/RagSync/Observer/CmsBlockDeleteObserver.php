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
use Lmarcho\RagSync\Model\Queue;

class CmsBlockDeleteObserver implements ObserverInterface
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
        /** @var Block $block */
        $block = $observer->getEvent()->getObject();

        if (!$block || !$block->getId()) {
            return;
        }

        $this->queueService->queueCmsBlock((int)$block->getId(), 0, Queue::ACTION_DELETE);
    }
}
