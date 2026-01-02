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
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Psr\Log\LoggerInterface;

class SyncPromotionsCommand extends Command
{
    /**
     * @var CartRuleCollectionFactory
     */
    private CartRuleCollectionFactory $cartRuleCollectionFactory;

    /**
     * @var CatalogRuleCollectionFactory
     */
    private CatalogRuleCollectionFactory $catalogRuleCollectionFactory;

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
     * @param CartRuleCollectionFactory $cartRuleCollectionFactory
     * @param CatalogRuleCollectionFactory $catalogRuleCollectionFactory
     * @param Config $config
     * @param QueueService $queueService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRuleCollectionFactory $cartRuleCollectionFactory,
        CatalogRuleCollectionFactory $catalogRuleCollectionFactory,
        Config $config,
        QueueService $queueService,
        LoggerInterface $logger
    ) {
        $this->cartRuleCollectionFactory = $cartRuleCollectionFactory;
        $this->catalogRuleCollectionFactory = $catalogRuleCollectionFactory;
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
        $this->setName('ragsync:sync:promotions')
            ->setDescription('Queue all promotions (cart and catalog rules) for RAG sync')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Type to sync: cart, catalog, or all (default: all)',
                'all'
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

        if (!$this->config->isPromotionSyncEnabled()) {
            $output->writeln('<error>Promotion sync is disabled.</error>');
            return Command::FAILURE;
        }

        $type = $input->getOption('type');
        $ruleTypes = $this->config->getPromotionRuleTypes();

        $output->writeln('<info>Starting promotion sync...</info>');

        try {
            $cartRuleCount = 0;
            $catalogRuleCount = 0;

            if (($type === 'all' || $type === 'cart') && in_array('cart', $ruleTypes)) {
                $cartRuleCount = $this->syncCartRules($output);
            }

            if (($type === 'all' || $type === 'catalog') && in_array('catalog', $ruleTypes)) {
                $catalogRuleCount = $this->syncCatalogRules($output);
            }

            $output->writeln(sprintf(
                '<info>Successfully queued %d cart rules and %d catalog rules for sync.</info>',
                $cartRuleCount,
                $catalogRuleCount
            ));

            $this->logger->info('RagSync CLI: Promotions queued', [
                'cart_rules' => $cartRuleCount,
                'catalog_rules' => $catalogRuleCount,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: Promotion sync error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Sync cart rules
     *
     * @param OutputInterface $output
     * @return int
     */
    private function syncCartRules(OutputInterface $output): int
    {
        $collection = $this->cartRuleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        $now = new \DateTime();
        $collection->addFieldToFilter(
            ['from_date', 'from_date'],
            [['null' => true], ['lteq' => $now->format('Y-m-d')]]
        );
        $collection->addFieldToFilter(
            ['to_date', 'to_date'],
            [['null' => true], ['gteq' => $now->format('Y-m-d')]]
        );

        $count = 0;
        foreach ($collection as $rule) {
            $this->queueService->addToQueue(
                'cart_rule',
                (int)$rule->getId(),
                0,
                'upsert'
            );
            $count++;
        }

        $output->writeln(sprintf('<info>Queued %d cart rules.</info>', $count));
        return $count;
    }

    /**
     * Sync catalog rules
     *
     * @param OutputInterface $output
     * @return int
     */
    private function syncCatalogRules(OutputInterface $output): int
    {
        $collection = $this->catalogRuleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);

        $now = new \DateTime();
        $collection->addFieldToFilter(
            ['from_date', 'from_date'],
            [['null' => true], ['lteq' => $now->format('Y-m-d')]]
        );
        $collection->addFieldToFilter(
            ['to_date', 'to_date'],
            [['null' => true], ['gteq' => $now->format('Y-m-d')]]
        );

        $count = 0;
        foreach ($collection as $rule) {
            $this->queueService->addToQueue(
                'catalog_rule',
                (int)$rule->getId(),
                0,
                'upsert'
            );
            $count++;
        }

        $output->writeln(sprintf('<info>Queued %d catalog rules.</info>', $count));
        return $count;
    }
}
