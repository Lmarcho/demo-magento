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

        // Get values from request (form fields)
        $webhookUrl = $this->getRequest()->getParam('webhook_url');
        $apiSecret = $this->getRequest()->getParam('api_secret');

        // Use form URL if provided, otherwise use saved config
        if (empty($webhookUrl)) {
            $webhookUrl = $this->config->getWebhookUrl();
        }

        // Use form secret if provided, otherwise use saved config
        if (empty($apiSecret)) {
            $apiSecret = $this->config->getApiSecret();
        }

        // Validate we have both required values
        if (empty($webhookUrl)) {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter Webhook Endpoint URL.'),
            ]);
        }

        if (empty($apiSecret)) {
            return $result->setData([
                'success' => false,
                'message' => __('Please enter API Secret Key.'),
            ]);
        }

        // Test connection with credentials
        $response = $this->webhookSender->testConnectionWithCredentials($webhookUrl, $apiSecret);

        // Extract error message from response body if available
        $message = __('Connection successful!');
        if (!$response->isSuccess()) {
            $body = $response->getBody();
            if (is_array($body) && isset($body['message'])) {
                $message = sprintf('[HTTP %d] %s', $response->getStatusCode(), $body['message']);
            } else {
                $message = $response->getErrorMessage() ?: sprintf('[HTTP %d] Unknown error', $response->getStatusCode());
            }
        }

        return $result->setData([
            'success' => $response->isSuccess(),
            'message' => $message,
            'latency' => $response->getDurationMs(),
            'status_code' => $response->getStatusCode(),
        ]);
    }
}
