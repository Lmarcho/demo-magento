<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Category;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\Queue;

class CategoryDeleteObserver implements ObserverInterface
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
        /** @var Category $category */
        $category = $observer->getEvent()->getCategory();

        if (!$category || !$category->getId()) {
            return;
        }

        $this->queueService->queueCategory(
            (int)$category->getId(),
            (int)$category->getStoreId(),
            Queue::ACTION_DELETE
        );
    }
}
