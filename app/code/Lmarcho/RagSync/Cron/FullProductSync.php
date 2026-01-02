<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\ProductBuilder;
use Psr\Log\LoggerInterface;

class FullProductSync
{
    private const BATCH_SIZE = 100;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var ProductBuilder
     */
    private ProductBuilder $productBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param ProductBuilder $productBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Config $config,
        QueueService $queueService,
        ProductBuilder $productBuilder,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->config = $config;
        $this->queueService = $queueService;
        $this->productBuilder = $productBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute full product sync
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isProductSyncEnabled()) {
            return;
        }

        $this->logger->info('RagSync: Starting full product sync');

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['status', 'visibility']);

        // Apply filters based on config
        if (!$this->config->includeDisabledProducts()) {
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        }

        if (!$this->config->includeNotVisibleProducts()) {
            $collection->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        }

        // Exclude categories if configured
        $excludedCategoryIds = $this->config->getExcludedCategoryIds();
        // Note: Category exclusion is handled in ProductBuilder::shouldSync

        $totalCount = $collection->getSize();
        $page = 1;
        $queuedCount = 0;

        do {
            $collection->clear();
            $collection->setPageSize(self::BATCH_SIZE);
            $collection->setCurPage($page);

            foreach ($collection as $product) {
                if ($this->productBuilder->shouldSync($product)) {
                    $this->queueService->queueProduct((int)$product->getId());
                    $queuedCount++;
                }
            }

            $page++;

            // Clear memory
            gc_collect_cycles();

        } while ($collection->getCurPage() < $collection->getLastPageNumber());

        $this->logger->info('RagSync: Full product sync completed', [
            'total_products' => $totalCount,
            'queued' => $queuedCount,
        ]);
    }
}
