<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Product;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\Queue;

class ProductDeleteObserver implements ObserverInterface
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
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();

        if (!$product || !$product->getId()) {
            return;
        }

        $this->queueService->queueProduct(
            (int)$product->getId(),
            (int)$product->getStoreId(),
            Queue::ACTION_DELETE
        );
    }
}
