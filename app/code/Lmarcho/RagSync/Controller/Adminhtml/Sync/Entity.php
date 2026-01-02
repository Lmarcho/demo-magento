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
use Lmarcho\RagSync\Cron\FullProductSync;
use Lmarcho\RagSync\Cron\FullCmsSync;
use Lmarcho\RagSync\Cron\FullCategorySync;
use Lmarcho\RagSync\Cron\PromotionSync;
use Psr\Log\LoggerInterface;

class Entity extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::sync';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var FullProductSync
     */
    private FullProductSync $productSync;

    /**
     * @var FullCmsSync
     */
    private FullCmsSync $cmsSync;

    /**
     * @var FullCategorySync
     */
    private FullCategorySync $categorySync;

    /**
     * @var PromotionSync
     */
    private PromotionSync $promotionSync;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param FullProductSync $productSync
     * @param FullCmsSync $cmsSync
     * @param FullCategorySync $categorySync
     * @param PromotionSync $promotionSync
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FullProductSync $productSync,
        FullCmsSync $cmsSync,
        FullCategorySync $categorySync,
        PromotionSync $promotionSync,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productSync = $productSync;
        $this->cmsSync = $cmsSync;
        $this->categorySync = $categorySync;
        $this->promotionSync = $promotionSync;
        $this->logger = $logger;
    }

    /**
     * Execute entity sync
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $type = $this->getRequest()->getParam('type');

        try {
            switch ($type) {
                case 'products':
                    $this->productSync->execute();
                    $message = __('Products queued for sync successfully!');
                    break;

                case 'cms_pages':
                case 'cms_blocks':
                    $this->cmsSync->execute();
                    $message = __('CMS content queued for sync successfully!');
                    break;

                case 'categories':
                    $this->categorySync->execute();
                    $message = __('Categories queued for sync successfully!');
                    break;

                case 'promotions':
                    $this->promotionSync->execute();
                    $message = __('Promotions queued for sync successfully!');
                    break;

                default:
                    return $result->setData([
                        'success' => false,
                        'message' => __('Unknown entity type: %1', $type),
                    ]);
            }

            return $result->setData([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RagSync: Entity sync failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $result->setData([
                'success' => false,
                'message' => __('Sync failed: %1', $e->getMessage()),
            ]);
        }
    }
}
