<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Controller\Adminhtml\Client;

use Lmarcho\CommerceMcp\Model\Authentication\ClientManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Psr\Log\LoggerInterface;

class Generate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Lmarcho_CommerceMcp::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ClientManager $clientManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $name = trim((string)$this->getRequest()->getParam('name'));
        $expiresAt = trim((string)$this->getRequest()->getParam('expires_at'));

        if ($name === '') {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Client name is required.'),
            ]);
        }

        try {
            $token = $this->clientManager->create($name, $expiresAt !== '' ? $expiresAt : null);

            return $result->setData([
                'success' => true,
                'client_name' => $name,
                'token' => $token,
                'message' => (string)__('Store this token now. It will not be shown again.'),
            ]);
        } catch (AlreadyExistsException $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__(
                    'A Commerce MCP client with this name already exists. Use a unique client name.'
                ),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Commerce MCP admin token generation failed', [
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to generate an access token. Check the logs for details.'),
            ]);
        }
    }
}
