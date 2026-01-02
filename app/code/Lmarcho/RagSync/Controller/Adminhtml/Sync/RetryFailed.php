<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\ResourceModel\Queue\CollectionFactory as QueueCollectionFactory;
use Psr\Log\LoggerInterface;

class RetryFailed extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::sync';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var QueueCollectionFactory
     */
    private QueueCollectionFactory $queueCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param QueueCollectionFactory $queueCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        QueueCollectionFactory $queueCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute retry failed items
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $collection = $this->queueCollectionFactory->create();
            $collection->addStatusFilter([Queue::STATUS_FAILED, Queue::STATUS_DEAD]);

            $count = 0;
            foreach ($collection as $item) {
                $item->setStatus(Queue::STATUS_PENDING);
                $item->setAttempts(0);
                $item->setErrorMessage(null);
                $item->save();
                $count++;
            }

            $this->logger->info('RagSync: Retried failed items', ['count' => $count]);

            return $result->setData([
                'success' => true,
                'message' => __('%1 items queued for retry.', $count),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Retry failed items error', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Failed to retry items: %1', $e->getMessage()),
            ]);
        }
    }
}
