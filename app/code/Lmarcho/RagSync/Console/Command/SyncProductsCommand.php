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
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\QueueService;
use Psr\Log\LoggerInterface;

class SyncProductsCommand extends Command
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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Config $config,
        QueueService $queueService,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
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
        $this->setName('ragsync:sync:products')
            ->setDescription('Queue all products for RAG sync')
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
                'Comma-separated product IDs to sync',
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

        if (!$this->config->isProductSyncEnabled()) {
            $output->writeln('<error>Product sync is disabled.</error>');
            return Command::FAILURE;
        }

        $storeId = $input->getOption('store');
        $productIds = $input->getOption('ids');

        $output->writeln('<info>Starting product sync...</info>');

        try {
            $collection = $this->productCollectionFactory->create();

            if ($storeId !== null) {
                $collection->setStoreId((int)$storeId);
            }

            if ($productIds !== null) {
                $ids = array_map('intval', explode(',', $productIds));
                $collection->addFieldToFilter('entity_id', ['in' => $ids]);
            }

            $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

            $totalCount = $collection->getSize();
            $output->writeln(sprintf('<info>Found %d products to sync.</info>', $totalCount));

            $processedCount = 0;
            $collection->setPageSize(self::BATCH_SIZE);
            $lastPage = $collection->getLastPageNumber();

            for ($currentPage = 1; $currentPage <= $lastPage; $currentPage++) {
                $collection->setCurPage($currentPage);
                $collection->load();

                foreach ($collection as $product) {
                    $this->queueService->addToQueue(
                        Queue::ENTITY_TYPE_PRODUCT,
                        (int)$product->getId(),
                        (int)($storeId ?? $product->getStoreId()),
                        Queue::ACTION_SAVE
                    );
                    $processedCount++;
                }

                $output->writeln(sprintf(
                    '<info>Processed %d/%d products...</info>',
                    $processedCount,
                    $totalCount
                ));

                $collection->clear();
            }

            $output->writeln(sprintf(
                '<info>Successfully queued %d products for sync.</info>',
                $processedCount
            ));

            $this->logger->info('RagSync CLI: Products queued', ['count' => $processedCount]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: Product sync error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
