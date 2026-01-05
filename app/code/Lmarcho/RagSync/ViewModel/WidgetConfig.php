<?php
/**
 * Lmarcho RagSync Module - Widget Config ViewModel
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Lmarcho\RagSync\Model\Config;

class WidgetConfig implements ArgumentInterface
{
    private const CHAT_SESSION_COOKIE = 'ragsync_chat_session';
    private const COOKIE_DURATION = 86400 * 30; // 30 days

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
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * @var SessionManagerInterface
     */
    private SessionManagerInterface $sessionManager;

    /**
     * @var string|null
     */
    private ?string $chatSessionId = null;

    /**
     * @param Config $config
     * @param LayoutInterface $layout
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     */
    public function __construct(
        Config $config,
        LayoutInterface $layout,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager
    ) {
        $this->config = $config;
        $this->layout = $layout;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
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
     * Get chat session data as JSON string
     *
     * Contains session ID for conversation persistence and customer info for personalization
     *
     * @return string
     */
    public function getChatSessionJson(): string
    {
        $session = [
            'sessionId' => $this->getChatSessionId(),
            'customerId' => null,
            'customerEmail' => null,
            'customerName' => null,
            'isLoggedIn' => $this->customerSession->isLoggedIn(),
            'storeId' => $this->getStoreId(),
        ];

        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $session['customerId'] = (int)$customer->getId();
            $session['customerEmail'] = $customer->getEmail();
            $session['customerName'] = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            $session['customerGroupId'] = (int)$this->customerSession->getCustomerGroupId();
        }

        return json_encode($session, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Get or create chat session ID
     *
     * For logged-in users, the session is tied to customer ID.
     * For guests, a UUID is generated and stored in a cookie.
     *
     * @return string
     */
    public function getChatSessionId(): string
    {
        if ($this->chatSessionId !== null) {
            return $this->chatSessionId;
        }

        // For logged-in users, use a customer-based session ID
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            $this->chatSessionId = 'customer_' . $customerId;
            return $this->chatSessionId;
        }

        // For guests, use cookie-based session ID
        $this->chatSessionId = $this->cookieManager->getCookie(self::CHAT_SESSION_COOKIE);

        if (empty($this->chatSessionId)) {
            $this->chatSessionId = $this->generateSessionId();
            $this->setChatSessionCookie($this->chatSessionId);
        }

        return $this->chatSessionId;
    }

    /**
     * Generate a new session ID (UUID v4)
     *
     * @return string
     */
    private function generateSessionId(): string
    {
        return sprintf(
            'guest_%s%s-%s-%s-%s-%s%s%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2))
        );
    }

    /**
     * Set chat session cookie
     *
     * @param string $sessionId
     * @return void
     */
    private function setChatSessionCookie(string $sessionId): void
    {
        try {
            $metadata = $this->cookieMetadataFactory
                ->createPublicCookieMetadata()
                ->setDuration(self::COOKIE_DURATION)
                ->setPath($this->sessionManager->getCookiePath())
                ->setDomain($this->sessionManager->getCookieDomain())
                ->setHttpOnly(false) // Allow JS access for widget
                ->setSecure($this->isSecure());

            $this->cookieManager->setPublicCookie(
                self::CHAT_SESSION_COOKIE,
                $sessionId,
                $metadata
            );
        } catch (\Exception $e) {
            // Cookie setting failed, but we can still use the session ID for this request
        }
    }

    /**
     * Check if connection is secure
     *
     * @return bool
     */
    private function isSecure(): bool
    {
        try {
            return $this->storeManager->getStore()->isCurrentlySecure();
        } catch (\Exception $e) {
            return false;
        }
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
