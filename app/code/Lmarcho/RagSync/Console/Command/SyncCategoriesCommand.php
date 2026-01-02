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
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Psr\Log\LoggerInterface;

class SyncCategoriesCommand extends Command
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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        Config $config,
        QueueService $queueService,
        LoggerInterface $logger
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
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
        $this->setName('ragsync:sync:categories')
            ->setDescription('Queue all categories for RAG sync')
            ->addOption(
                'store',
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID to sync (default: all stores)',
                null
            )
            ->addOption(
                'ids',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated category IDs to sync',
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

        if (!$this->config->isCategorySyncEnabled()) {
            $output->writeln('<error>Category sync is disabled.</error>');
            return Command::FAILURE;
        }

        $storeId = $input->getOption('store');
        $categoryIds = $input->getOption('ids');

        $output->writeln('<info>Starting category sync...</info>');

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect('*');
            $collection->addAttributeToFilter('is_active', 1);
            // Exclude root categories (level 0 and 1)
            $collection->addAttributeToFilter('level', ['gt' => 1]);

            if ($storeId !== null) {
                $collection->setStoreId((int)$storeId);
            }

            if ($categoryIds !== null) {
                $ids = array_map('intval', explode(',', $categoryIds));
                $collection->addFieldToFilter('entity_id', ['in' => $ids]);
            }

            $count = 0;
            foreach ($collection as $category) {
                $this->queueService->addToQueue(
                    'category',
                    (int)$category->getId(),
                    (int)($storeId ?? $category->getStoreId()),
                    'upsert'
                );
                $count++;
            }

            $output->writeln(sprintf(
                '<info>Successfully queued %d categories for sync.</info>',
                $count
            ));

            $this->logger->info('RagSync CLI: Categories queued', ['count' => $count]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: Category sync error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
