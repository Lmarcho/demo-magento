<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Controller\Customer;

use Lmarcho\CommerceMcp\Api\CustomerAssertionServiceInterface;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;

class Assertion implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly Session $customerSession,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerAssertionServiceInterface $assertionService
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->json(['error' => 'MCP server disabled'], 503);
        }
        if (!$this->customerSession->isLoggedIn()) {
            return $this->json(['error' => 'Customer login required'], 401);
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->json(['error' => 'Invalid form key'], 400);
        }

        $store = $this->storeManager->getStore();
        $assertion = $this->assertionService->issue(
            (int)$this->customerSession->getCustomerId(),
            (int)$store->getId(),
            (int)$store->getWebsiteId()
        );

        return $this->json([
            'customer_assertion' => $assertion,
            'expires_in' => $this->config->getCustomerAssertionLifetimeSeconds(),
        ]);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->jsonFactory->create()
            ->setHttpResponseCode($status)
            ->setData($data);
    }
}
