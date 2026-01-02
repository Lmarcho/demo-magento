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
use Lmarcho\RagSync\Model\WebhookSender;
use Lmarcho\RagSync\Model\Config;

class TestConnection extends Action
{
    public const ADMIN_RESOURCE = 'Lmarcho_RagSync::sync';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var WebhookSender
     */
    private WebhookSender $webhookSender;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param WebhookSender $webhookSender
     * @param Config $config
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        WebhookSender $webhookSender,
        Config $config
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->webhookSender = $webhookSender;
        $this->config = $config;
    }

    /**
     * Execute test connection
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isConnectionConfigured()) {
            return $result->setData([
                'success' => false,
                'message' => __('Connection not configured. Please set webhook URL, tenant ID, and API secret.'),
            ]);
        }

        $response = $this->webhookSender->testConnection();

        return $result->setData([
            'success' => $response->isSuccess(),
            'message' => $response->isSuccess() ? __('Connection successful!') : $response->getErrorMessage(),
            'latency' => $response->getDurationMs(),
            'status_code' => $response->getStatusCode(),
        ]);
    }
}
