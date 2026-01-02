<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Controller\Adminhtml\Queue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Lmarcho\RagSync\Model\Queue;
use Lmarcho\RagSync\Model\ResourceModel\Queue\CollectionFactory;
use Psr\Log\LoggerInterface;

class MassRetry extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::queue';

    /**
     * @var Filter
     */
    private Filter $filter;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute mass retry action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $item) {
                $item->setStatus(Queue::STATUS_PENDING);
                $item->setAttempts(0);
                $item->setErrorMessage(null);
                $item->save();
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                __('%1 item(s) have been queued for retry.', $count)
            );

            $this->logger->info('RagSync: Mass retry executed', ['count' => $count]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while retrying items: %1', $e->getMessage())
            );
            $this->logger->error('RagSync: Mass retry error', ['error' => $e->getMessage()]);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
