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
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Psr\Log\LoggerInterface;

class ClearSent extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::sync';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param QueueResource $queueResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        QueueResource $queueResource,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->queueResource = $queueResource;
        $this->logger = $logger;
    }

    /**
     * Execute clear sent items
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Clear items older than 0 days (all sent items)
            $count = $this->queueResource->cleanupOldItems(0);

            $this->logger->info('RagSync: Cleared sent items', ['count' => $count]);

            return $result->setData([
                'success' => true,
                'message' => __('%1 sent items cleared.', $count),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Clear sent items error', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Failed to clear sent items: %1', $e->getMessage()),
            ]);
        }
    }
}
