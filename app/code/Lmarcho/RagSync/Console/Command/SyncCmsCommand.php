<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Psr\Log\LoggerInterface;

class SyncCmsCommand extends Command
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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param LoggerInterface $logger
     */
    public function __construct(
        PageCollectionFactory $pageCollectionFactory,
        BlockCollectionFactory $blockCollectionFactory,
        Config $config,
        QueueService $queueService,
        LoggerInterface $logger
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->config = $config;
        $this->queueService = $queueService;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('ragsync:sync:cms')
            ->setDescription('Queue all CMS pages and blocks for RAG sync')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Type to sync: pages, blocks, or all (default: all)',
                'all'
            )
            ->addOption(
                'store',
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID to sync (default: all stores)',
                null
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>RAG Sync module is disabled.</error>');
            return Command::FAILURE;
        }

        $type = $input->getOption('type');
        $storeId = $input->getOption('store');

        $output->writeln('<info>Starting CMS sync...</info>');

        try {
            $pageCount = 0;
            $blockCount = 0;

            if ($type === 'all' || $type === 'pages') {
                if (!$this->config->isCmsPageSyncEnabled()) {
                    $output->writeln('<comment>CMS Page sync is disabled, skipping...</comment>');
                } else {
                    $pageCount = $this->syncPages($output, $storeId);
                }
            }

            if ($type === 'all' || $type === 'blocks') {
                if (!$this->config->isCmsBlockSyncEnabled()) {
                    $output->writeln('<comment>CMS Block sync is disabled, skipping...</comment>');
                } else {
                    $blockCount = $this->syncBlocks($output, $storeId);
                }
            }

            $output->writeln(sprintf(
                '<info>Successfully queued %d pages and %d blocks for sync.</info>',
                $pageCount,
                $blockCount
            ));

            $this->logger->info('RagSync CLI: CMS queued', [
                'pages' => $pageCount,
                'blocks' => $blockCount,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: CMS sync error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Sync CMS pages
     *
     * @param OutputInterface $output
     * @param string|null $storeId
     * @return int
     */
    private function syncPages(OutputInterface $output, ?string $storeId): int
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        if ($storeId !== null) {
            $collection->addStoreFilter((int)$storeId);
        }

        $count = 0;
        foreach ($collection as $page) {
            $stores = $page->getStoreId();
            if (is_array($stores)) {
                foreach ($stores as $store) {
                    $this->queueService->addToQueue(
                        'cms_page',
                        (int)$page->getId(),
                        (int)$store,
                        'upsert'
                    );
                }
            } else {
                $this->queueService->addToQueue(
                    'cms_page',
                    (int)$page->getId(),
                    (int)($storeId ?? 0),
                    'upsert'
                );
            }
            $count++;
        }

        $output->writeln(sprintf('<info>Queued %d CMS pages.</info>', $count));
        return $count;
    }

    /**
     * Sync CMS blocks
     *
     * @param OutputInterface $output
     * @param string|null $storeId
     * @return int
     */
    private function syncBlocks(OutputInterface $output, ?string $storeId): int
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        if ($storeId !== null) {
            $collection->addStoreFilter((int)$storeId);
        }

        $count = 0;
        foreach ($collection as $block) {
            $stores = $block->getStoreId();
            if (is_array($stores)) {
                foreach ($stores as $store) {
                    $this->queueService->addToQueue(
                        'cms_block',
                        (int)$block->getId(),
                        (int)$store,
                        'upsert'
                    );
                }
            } else {
                $this->queueService->addToQueue(
                    'cms_block',
                    (int)$block->getId(),
                    (int)($storeId ?? 0),
                    'upsert'
                );
            }
            $count++;
        }

        $output->writeln(sprintf('<info>Queued %d CMS blocks.</info>', $count));
        return $count;
    }
}
