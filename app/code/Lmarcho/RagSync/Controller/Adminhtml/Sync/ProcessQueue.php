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
use Lmarcho\RagSync\Cron\ProcessQueue as ProcessQueueCron;
use Psr\Log\LoggerInterface;

class ProcessQueue extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::sync';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ProcessQueueCron
     */
    private ProcessQueueCron $processQueue;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProcessQueueCron $processQueue
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProcessQueueCron $processQueue,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->processQueue = $processQueue;
        $this->logger = $logger;
    }

    /**
     * Execute queue processing
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $this->processQueue->execute();

            return $result->setData([
                'success' => true,
                'message' => __('Queue processed successfully!'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Manual queue processing failed', [
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Queue processing failed: %1', $e->getMessage()),
            ]);
        }
    }
}
