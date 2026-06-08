<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Store;

use Lmarcho\CommerceMcp\Api\Data\StoreContextInterface;
use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreIsInactiveException;
use Magento\Store\Model\StoreManagerInterface;

class StoreContextResolver implements StoreContextResolverInterface
{
    private const XML_PATH_LOCALE = 'general/locale/code';
    private const XML_PATH_TIMEZONE = 'general/locale/timezone';

    public function __construct(
        private readonly Config $config,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StockResolverInterface $stockResolver
    ) {
    }

    public function resolve(string $storeCode): StoreContextInterface
    {
        $storeCode = trim($storeCode);
        if ($storeCode === '' || preg_match('/\A[a-zA-Z0-9_]+\z/', $storeCode) !== 1) {
            throw $this->invalidStore('INVALID_STORE_CODE');
        }
        if (!in_array($storeCode, $this->config->getAllowedStoreCodes(), true)) {
            throw $this->invalidStore('STORE_NOT_ALLOWED');
        }

        try {
            $activeStore = $this->storeRepository->getActiveStoreByCode($storeCode);
            $store = $this->storeManager->getStore((int)$activeStore->getId());
            $website = $this->storeManager->getWebsite((int)$activeStore->getWebsiteId());
        } catch (NoSuchEntityException|StoreIsInactiveException) {
            throw $this->invalidStore('STORE_NOT_AVAILABLE');
        }

        $websiteCode = (string)$website->getCode();
        try {
            $stock = $this->stockResolver->execute(
                SalesChannelInterface::TYPE_WEBSITE,
                $websiteCode
            );
        } catch (\Throwable) {
            throw new JsonRpcException(
                'Store inventory context is unavailable',
                -32010,
                null,
                ['error_code' => 'STOCK_CONTEXT_UNAVAILABLE']
            );
        }

        return new StoreContext(
            (int)$store->getId(),
            (string)$store->getCode(),
            (string)$store->getName(),
            (int)$website->getId(),
            $websiteCode,
            (string)$store->getCurrentCurrencyCode(),
            (string)$this->scopeConfig->getValue(
                self::XML_PATH_LOCALE,
                ScopeInterface::SCOPE_STORE,
                $storeCode
            ),
            (string)$this->scopeConfig->getValue(
                self::XML_PATH_TIMEZONE,
                ScopeInterface::SCOPE_STORE,
                $storeCode
            ),
            (string)$store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true),
            (string)$store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true),
            SalesChannelInterface::TYPE_WEBSITE,
            $websiteCode,
            (int)$stock->getStockId()
        );
    }

    private function invalidStore(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Store is not available',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
