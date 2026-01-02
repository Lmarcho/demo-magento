<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\SalesRule\Model\Rule;
use Lmarcho\RagSync\Model\QueueService;
use Lmarcho\RagSync\Model\DataBuilder\PromotionBuilder;

class CartRuleSaveObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @var PromotionBuilder
     */
    private PromotionBuilder $promotionBuilder;

    /**
     * @param QueueService $queueService
     * @param PromotionBuilder $promotionBuilder
     */
    public function __construct(
        QueueService $queueService,
        PromotionBuilder $promotionBuilder
    ) {
        $this->queueService = $queueService;
        $this->promotionBuilder = $promotionBuilder;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Rule $rule */
        $rule = $observer->getEvent()->getRule();

        if (!$rule || !$rule->getId()) {
            return;
        }

        // Check if rule should be synced based on config
        if (!$this->promotionBuilder->shouldSyncCartRule($rule)) {
            return;
        }

        $this->queueService->queueCartRule((int)$rule->getId());
    }
}
