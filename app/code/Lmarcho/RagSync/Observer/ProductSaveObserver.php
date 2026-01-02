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
use Lmarcho\RagSync\Model\DataBuilder\ProductBuilder;

class ProductSaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var ProductBuilder
     */
    private ProductBuilder $productBuilder;

    /**
     * @param QueueService $queueService
     * @param ProductBuilder $productBuilder
     */
    public function __construct(
        QueueService $queueService,
        ProductBuilder $productBuilder
    ) {
        $this->queueService = $queueService;
        $this->productBuilder = $productBuilder;
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

        $storeId = (int)$product->getStoreId();

        // Check if product should be synced based on config
        if (!$this->productBuilder->shouldSync($product, $storeId)) {
            return;
        }

        $this->queueService->queueProduct((int)$product->getId(), $storeId);
    }
}
