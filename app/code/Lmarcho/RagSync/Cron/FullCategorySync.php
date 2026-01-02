<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\CategoryBuilder;
use Psr\Log\LoggerInterface;

class FullCategorySync
{
    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CategoryBuilder
     */
    private CategoryBuilder $categoryBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param CategoryBuilder $categoryBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        Config $config,
        QueueService $queueService,
        CategoryBuilder $categoryBuilder,
        LoggerInterface $logger
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->config = $config;
        $this->queueService = $queueService;
        $this->categoryBuilder = $categoryBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute full category sync
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isCategorySyncEnabled()) {
            return;
        }

        $this->logger->info('RagSync: Starting full category sync');

        $minLevel = $this->config->getCategoryMinLevel();
        $includeInactive = $this->config->includeInactiveCategories();
        $excludedIds = $this->config->getExcludedCategoryIds();

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'is_active', 'level']);
        $collection->addAttributeToFilter('level', ['gteq' => $minLevel]);

        if (!$includeInactive) {
            $collection->addAttributeToFilter('is_active', 1);
        }

        if (!empty($excludedIds)) {
            $collection->addAttributeToFilter('entity_id', ['nin' => $excludedIds]);
        }

        $queued = 0;

        foreach ($collection as $category) {
            if ($this->categoryBuilder->shouldSync($category)) {
                $this->queueService->queueCategory((int)$category->getId());
                $queued++;
            }
        }

        $this->logger->info('RagSync: Full category sync completed', [
            'categories_queued' => $queued,
        ]);
    }
}
