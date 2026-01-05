<?php
/**
 * Lmarcho RagSync Module - Widget Config ViewModel
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Lmarcho\RagSync\Model\Config;

class WidgetConfig implements ArgumentInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Config $config
     * @param LayoutInterface $layout
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        LayoutInterface $layout,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->layout = $layout;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if widget should be displayed on current page
     *
     * @return bool
     */
    public function shouldDisplay(): bool
    {
        $storeId = $this->getStoreId();

        if (!$this->config->isWidgetEnabled($storeId)) {
            return false;
        }

        if (!$this->config->isConnectionConfigured($storeId)) {
            return false;
        }

        $excludedHandles = $this->config->getWidgetExcludePages($storeId);
        if (empty($excludedHandles)) {
            return true;
        }

        $currentHandles = $this->layout->getUpdate()->getHandles();
        foreach ($excludedHandles as $excludedHandle) {
            if (in_array($excludedHandle, $currentHandles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get widget script URL
     *
     * @return string
     */
    public function getScriptUrl(): string
    {
        return $this->config->getWidgetScriptUrl($this->getStoreId());
    }

    /**
     * Get widget config URL with tenant parameter
     *
     * @return string
     */
    public function getConfigUrl(): string
    {
        $storeId = $this->getStoreId();
        $configUrl = $this->config->getWidgetConfigUrl($storeId);
        $tenantSlug = $this->config->getWidgetTenantSlug($storeId);

        if (!empty($tenantSlug)) {
            $configUrl .= '?tenant=' . urlencode($tenantSlug);
        }

        return $configUrl;
    }

    /**
     * Get API base URL (scheme://host:port)
     *
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        $webhookUrl = $this->config->getWebhookUrl($this->getStoreId());
        if (empty($webhookUrl)) {
            return '';
        }

        $parsed = parse_url($webhookUrl);
        if (!isset($parsed['scheme'], $parsed['host'])) {
            return '';
        }

        $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $baseUrl .= ':' . $parsed['port'];
        }

        return $baseUrl;
    }

    /**
     * Get customer context as JSON string
     *
     * @return string
     */
    public function getCustomerContextJson(): string
    {
        $storeId = $this->getStoreId();

        if (!$this->config->isWidgetCustomerContextEnabled($storeId)) {
            return 'null';
        }

        $context = [
            'isLoggedIn' => $this->customerSession->isLoggedIn(),
            'groupId' => null,
            'storeId' => $storeId,
            'storeCode' => $this->getStoreCode(),
        ];

        if ($this->customerSession->isLoggedIn()) {
            $context['groupId'] = (int)$this->customerSession->getCustomerGroupId();
        }

        return json_encode($context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Get current store ID
     *
     * @return int
     */
    private function getStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get current store code
     *
     * @return string
     */
    private function getStoreCode(): string
    {
        try {
            return $this->storeManager->getStore()->getCode();
        } catch (\Exception $e) {
            return 'default';
        }
    }
}
