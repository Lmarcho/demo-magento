<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\CmsPageBuilder;
use Lmarcho\RagSync\Model\DataBuilder\CmsBlockBuilder;
use Psr\Log\LoggerInterface;

class FullCmsSync
{
    /**
     * @var PageCollectionFactory
     */
    private PageCollectionFactory $pageCollectionFactory;

    /**
     * @var BlockCollectionFactory
     */
    private BlockCollectionFactory $blockCollectionFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var CmsPageBuilder
     */
    private CmsPageBuilder $cmsPageBuilder;

    /**
     * @var CmsBlockBuilder
     */
    private CmsBlockBuilder $cmsBlockBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param CmsPageBuilder $cmsPageBuilder
     * @param CmsBlockBuilder $cmsBlockBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageCollectionFactory $pageCollectionFactory,
        BlockCollectionFactory $blockCollectionFactory,
        Config $config,
        QueueService $queueService,
        CmsPageBuilder $cmsPageBuilder,
        CmsBlockBuilder $cmsBlockBuilder,
        LoggerInterface $logger
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->config = $config;
        $this->queueService = $queueService;
        $this->cmsPageBuilder = $cmsPageBuilder;
        $this->cmsBlockBuilder = $cmsBlockBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute full CMS sync
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->logger->info('RagSync: Starting full CMS sync');

        $pagesQueued = 0;
        $blocksQueued = 0;

        // Sync CMS Pages
        if ($this->config->isCmsPageSyncEnabled()) {
            $pagesQueued = $this->syncPages();
        }

        // Sync CMS Blocks
        if ($this->config->isCmsBlockSyncEnabled()) {
            $blocksQueued = $this->syncBlocks();
        }

        $this->logger->info('RagSync: Full CMS sync completed', [
            'pages_queued' => $pagesQueued,
            'blocks_queued' => $blocksQueued,
        ]);
    }

    /**
     * Sync CMS pages
     *
     * @return int Number of pages queued
     */
    private function syncPages(): int
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        $queued = 0;

        foreach ($collection as $page) {
            if ($this->cmsPageBuilder->shouldSync($page)) {
                $this->queueService->queueCmsPage((int)$page->getId());
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Sync CMS blocks
     *
     * @return int Number of blocks queued
     */
    private function syncBlocks(): int
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        $queued = 0;

        foreach ($collection as $block) {
            if ($this->cmsBlockBuilder->shouldSync($block)) {
                $this->queueService->queueCmsBlock((int)$block->getId());
                $queued++;
            }
        }

        return $queued;
    }
}
