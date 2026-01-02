<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as CartRuleCollectionFactory;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\PromotionBuilder;
use Psr\Log\LoggerInterface;

class PromotionSync
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
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var PromotionBuilder
     */
    private PromotionBuilder $promotionBuilder;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CartRuleCollectionFactory $cartRuleCollectionFactory
     * @param CatalogRuleCollectionFactory $catalogRuleCollectionFactory
     * @param DateTime $dateTime
     * @param Config $config
     * @param QueueService $queueService
     * @param PromotionBuilder $promotionBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRuleCollectionFactory $cartRuleCollectionFactory,
        CatalogRuleCollectionFactory $catalogRuleCollectionFactory,
        DateTime $dateTime,
        Config $config,
        QueueService $queueService,
        PromotionBuilder $promotionBuilder,
        LoggerInterface $logger
    ) {
        $this->cartRuleCollectionFactory = $cartRuleCollectionFactory;
        $this->catalogRuleCollectionFactory = $catalogRuleCollectionFactory;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->queueService = $queueService;
        $this->promotionBuilder = $promotionBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute promotions sync
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isPromotionSyncEnabled()) {
            return;
        }

        $this->logger->info('RagSync: Starting promotions sync');

        $cartRulesQueued = 0;
        $catalogRulesQueued = 0;

        // Sync Cart Price Rules
        if ($this->config->shouldSyncCartRules()) {
            $cartRulesQueued = $this->syncCartRules();
        }

        // Sync Catalog Price Rules
        if ($this->config->shouldSyncCatalogRules()) {
            $catalogRulesQueued = $this->syncCatalogRules();
        }

        $this->logger->info('RagSync: Promotions sync completed', [
            'cart_rules_queued' => $cartRulesQueued,
            'catalog_rules_queued' => $catalogRulesQueued,
        ]);
    }

    /**
     * Sync cart price rules
     *
     * @return int Number of rules queued
     */
    private function syncCartRules(): int
    {
        $collection = $this->cartRuleCollectionFactory->create();

        // Filter by active status if configured
        if (!$this->config->includeInactivePromotions()) {
            $collection->addFieldToFilter('is_active', 1);
        }

        // Filter by date if not including expired
        if (!$this->config->includeExpiredPromotions()) {
            $now = $this->dateTime->gmtDate('Y-m-d');
            $collection->addFieldToFilter(
                ['to_date', 'to_date'],
                [
                    ['gteq' => $now],
                    ['null' => true],
                ]
            );
        }

        $queued = 0;

        foreach ($collection as $rule) {
            if ($this->promotionBuilder->shouldSyncCartRule($rule)) {
                $this->queueService->queueCartRule((int)$rule->getId());
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Sync catalog price rules
     *
     * @return int Number of rules queued
     */
    private function syncCatalogRules(): int
    {
        $collection = $this->catalogRuleCollectionFactory->create();

        // Filter by active status if configured
        if (!$this->config->includeInactivePromotions()) {
            $collection->addFieldToFilter('is_active', 1);
        }

        // Filter by date if not including expired
        if (!$this->config->includeExpiredPromotions()) {
            $now = $this->dateTime->gmtDate('Y-m-d');
            $collection->addFieldToFilter(
                ['to_date', 'to_date'],
                [
                    ['gteq' => $now],
                    ['null' => true],
                ]
            );
        }

        $queued = 0;

        foreach ($collection as $rule) {
            if ($this->promotionBuilder->shouldSyncCatalogRule($rule)) {
                $this->queueService->queueCatalogRule((int)$rule->getId());
                $queued++;
            }
        }

        return $queued;
    }
}
