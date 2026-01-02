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
use Lmarcho\RagSync\Model\ResourceModel\Queue\CollectionFactory;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Psr\Log\LoggerInterface;

class MassDelete extends Action
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
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param QueueResource $queueResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        QueueResource $queueResource,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->queueResource = $queueResource;
        $this->logger = $logger;
    }

    /**
     * Execute mass delete action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;

            foreach ($collection as $item) {
                $this->queueResource->delete($item);
                $count++;
            }

            $this->messageManager->addSuccessMessage(
                __('%1 item(s) have been deleted.', $count)
            );

            $this->logger->info('RagSync: Mass delete executed', ['count' => $count]);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while deleting items: %1', $e->getMessage())
            );
            $this->logger->error('RagSync: Mass delete error', ['error' => $e->getMessage()]);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
