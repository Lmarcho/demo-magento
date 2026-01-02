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
use Lmarcho\RagSync\Model\Queue;

class CartRuleDeleteObserver implements ObserverInterface
{
    /**
     * @var QueueService
     */
    private QueueService $queueService;

    /**
     * @param QueueService $queueService
     */
    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
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

        $this->queueService->queueCartRule((int)$rule->getId(), 0, Queue::ACTION_DELETE);
    }
}
