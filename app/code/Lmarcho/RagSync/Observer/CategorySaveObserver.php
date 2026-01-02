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
use Lmarcho\RagSync\Model\DataBuilder\CategoryBuilder;

class CategorySaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CategoryBuilder
     */
    private CategoryBuilder $categoryBuilder;

    /**
     * @param QueueService $queueService
     * @param CategoryBuilder $categoryBuilder
     */
    public function __construct(
        QueueService $queueService,
        CategoryBuilder $categoryBuilder
    ) {
        $this->queueService = $queueService;
        $this->categoryBuilder = $categoryBuilder;
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

        $storeId = (int)$category->getStoreId();

        // Check if category should be synced based on config
        if (!$this->categoryBuilder->shouldSync($category, $storeId)) {
            return;
        }

        $this->queueService->queueCategory((int)$category->getId(), $storeId);
    }
}
