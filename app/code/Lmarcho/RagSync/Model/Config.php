<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'rag_sync/';

    // General Settings
    private const XML_PATH_ENABLED = 'general/enabled';
    private const XML_PATH_ENVIRONMENT = 'general/environment';
    private const XML_PATH_DEBUG = 'general/debug';
    private const XML_PATH_LOG_RETENTION = 'general/log_retention_days';

    // Connection Settings
    private const XML_PATH_WEBHOOK_URL = 'connection/webhook_url';
    private const XML_PATH_TENANT_ID = 'connection/tenant_id';
    private const XML_PATH_API_SECRET = 'connection/api_secret';
    private const XML_PATH_TIMEOUT = 'connection/timeout';

    // Products Settings
    private const XML_PATH_PRODUCTS_ENABLED = 'products/enabled';
    private const XML_PATH_PRODUCTS_INCLUDE_DISABLED = 'products/include_disabled';
    private const XML_PATH_PRODUCTS_INCLUDE_NOT_VISIBLE = 'products/include_not_visible';
    private const XML_PATH_PRODUCTS_SYNC_ATTRIBUTES = 'products/sync_attributes';
    private const XML_PATH_PRODUCTS_EXCLUDE_CATEGORIES = 'products/exclude_categories';

    // CMS Pages Settings
    private const XML_PATH_CMS_PAGES_ENABLED = 'cms_pages/enabled';
    private const XML_PATH_CMS_PAGES_SYNC_MODE = 'cms_pages/sync_mode';
    private const XML_PATH_CMS_PAGES_IDENTIFIERS = 'cms_pages/identifiers';
    private const XML_PATH_CMS_PAGES_EXCLUDE = 'cms_pages/exclude_identifiers';

    // CMS Blocks Settings
    private const XML_PATH_CMS_BLOCKS_ENABLED = 'cms_blocks/enabled';
    private const XML_PATH_CMS_BLOCKS_IDENTIFIERS = 'cms_blocks/identifiers';

    // Categories Settings
    private const XML_PATH_CATEGORIES_ENABLED = 'categories/enabled';
    private const XML_PATH_CATEGORIES_MIN_LEVEL = 'categories/min_level';
    private const XML_PATH_CATEGORIES_INCLUDE_INACTIVE = 'categories/include_inactive';
    private const XML_PATH_CATEGORIES_INCLUDE_PRODUCT_COUNT = 'categories/include_product_count';

    // Promotions Settings
    private const XML_PATH_PROMOTIONS_ENABLED = 'promotions/enabled';
    private const XML_PATH_PROMOTIONS_RULE_TYPES = 'promotions/rule_types';
    private const XML_PATH_PROMOTIONS_INCLUDE_INACTIVE = 'promotions/include_inactive';
    private const XML_PATH_PROMOTIONS_INCLUDE_EXPIRED = 'promotions/include_expired';

    // Queue Settings
    private const XML_PATH_QUEUE_BATCH_SIZE = 'queue/batch_size';
    private const XML_PATH_QUEUE_MAX_RETRIES = 'queue/max_retries';
    private const XML_PATH_QUEUE_RETRY_DELAYS = 'queue/retry_delays';
    private const XML_PATH_QUEUE_CLEANUP_DAYS = 'queue/cleanup_days';

    // Schedule Settings
    private const XML_PATH_SCHEDULE_PRODUCTS = 'schedule/products_cron';
    private const XML_PATH_SCHEDULE_CMS = 'schedule/cms_cron';
    private const XML_PATH_SCHEDULE_CATEGORIES = 'schedule/categories_cron';
    private const XML_PATH_SCHEDULE_PROMOTIONS = 'schedule/promotions_cron';

    // Chat Widget Settings
    private const XML_PATH_WIDGET_ENABLED = 'chat_widget/enabled';
    private const XML_PATH_WIDGET_TENANT_SLUG = 'chat_widget/tenant_slug';
    private const XML_PATH_WIDGET_EXCLUDE_PAGES = 'chat_widget/exclude_pages';
    private const XML_PATH_WIDGET_CUSTOMER_CONTEXT = 'chat_widget/send_customer_context';

    // Widget URLs (derived from webhook URL)
    private const WIDGET_SCRIPT_PATH = '/widget/widget.iife.js';
    private const WIDGET_CONFIG_PATH = '/widget/config';
    private const WIDGET_API_PATH = '';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Get config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    private function getValue(string $path, ?int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config flag
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // ==================== General Settings ====================

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * Get environment
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return (string)$this->getValue(self::XML_PATH_ENVIRONMENT) ?: 'production';
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugEnabled(): bool
    {
        return $this->getFlag(self::XML_PATH_DEBUG);
    }

    /**
     * Get log retention days
     *
     * @return int
     */
    public function getLogRetentionDays(): int
    {
        return (int)($this->getValue(self::XML_PATH_LOG_RETENTION) ?: 30);
    }

    // ==================== Connection Settings ====================

    /**
     * Get webhook base URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWebhookUrl(?int $storeId = null): string
    {
        return rtrim((string)$this->getValue(self::XML_PATH_WEBHOOK_URL, $storeId), '/');
    }

    /**
     * Get tenant ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTenantId(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_TENANT_ID, $storeId);
    }

    /**
     * Get API secret
     *
     * Note: Value is automatically decrypted by Magento when using
     * backend_model="Magento\Config\Model\Config\Backend\Encrypted" in system.xml
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiSecret(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_SECRET, $storeId);
    }

    /**
     * Get connection timeout in seconds
     *
     * @return int
     */
    public function getConnectionTimeout(): int
    {
        return (int)($this->getValue(self::XML_PATH_TIMEOUT) ?: 30);
    }

    /**
     * Get full webhook endpoint URL
     *
     * The URL is used directly as configured - Laravel handles routing based on payload
     *
     * @param string $endpoint (kept for backward compatibility, not used)
     * @param int|null $storeId
     * @return string
     */
    public function getWebhookEndpoint(string $endpoint = '', ?int $storeId = null): string
    {
        return $this->getWebhookUrl($storeId);
    }

    // ==================== Products Settings ====================

    /**
     * Check if product sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isProductSyncEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_PRODUCTS_ENABLED, $storeId);
    }

    /**
     * Check if disabled products should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeDisabledProducts(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_PRODUCTS_INCLUDE_DISABLED, $storeId);
    }

    /**
     * Check if not visible products should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeNotVisibleProducts(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_PRODUCTS_INCLUDE_NOT_VISIBLE, $storeId);
    }

    /**
     * Get product attributes to sync
     *
     * @param int|null $storeId
     * @return array
     */
    public function getProductSyncAttributes(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_PRODUCTS_SYNC_ATTRIBUTES, $storeId);
        return $this->parseCommaSeparated($value);
    }

    /**
     * Get excluded category IDs
     *
     * @param int|null $storeId
     * @return array
     */
    public function getExcludedCategoryIds(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_PRODUCTS_EXCLUDE_CATEGORIES, $storeId);
        return array_map('intval', $this->parseCommaSeparated($value));
    }

    // ==================== CMS Pages Settings ====================

    /**
     * Check if CMS page sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCmsPageSyncEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_CMS_PAGES_ENABLED, $storeId);
    }

    /**
     * Get CMS pages sync mode
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCmsPagesSyncMode(?int $storeId = null): string
    {
        return (string)($this->getValue(self::XML_PATH_CMS_PAGES_SYNC_MODE, $storeId) ?: 'whitelist');
    }

    /**
     * Get CMS page identifiers to sync
     *
     * @param int|null $storeId
     * @return array
     */
    public function getCmsPagesIdentifiers(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_CMS_PAGES_IDENTIFIERS, $storeId);
        return $this->parseCommaSeparated($value);
    }

    /**
     * Get CMS page identifiers to exclude
     *
     * @param int|null $storeId
     * @return array
     */
    public function getCmsPagesExcludeIdentifiers(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_CMS_PAGES_EXCLUDE, $storeId);
        return $this->parseCommaSeparated($value);
    }

    /**
     * Check if CMS page should be synced based on config
     *
     * @param string $identifier
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCmsPage(string $identifier, ?int $storeId = null): bool
    {
        if (!$this->isCmsPageSyncEnabled($storeId)) {
            return false;
        }

        // Always exclude these
        $excludeList = $this->getCmsPagesExcludeIdentifiers($storeId);
        if (in_array($identifier, $excludeList, true)) {
            return false;
        }

        $mode = $this->getCmsPagesSyncMode($storeId);
        $identifiers = $this->getCmsPagesIdentifiers($storeId);

        if ($mode === 'whitelist') {
            return in_array($identifier, $identifiers, true);
        }

        // Blacklist mode
        return !in_array($identifier, $identifiers, true);
    }

    // ==================== CMS Blocks Settings ====================

    /**
     * Check if CMS block sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCmsBlockSyncEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_CMS_BLOCKS_ENABLED, $storeId);
    }

    /**
     * Get CMS block identifiers to sync
     *
     * @param int|null $storeId
     * @return array
     */
    public function getCmsBlocksIdentifiers(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_CMS_BLOCKS_IDENTIFIERS, $storeId);
        return $this->parseCommaSeparated($value);
    }

    /**
     * Check if CMS block should be synced
     *
     * @param string $identifier
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCmsBlock(string $identifier, ?int $storeId = null): bool
    {
        if (!$this->isCmsBlockSyncEnabled($storeId)) {
            return false;
        }

        $identifiers = $this->getCmsBlocksIdentifiers($storeId);

        // Empty means sync all
        if (empty($identifiers)) {
            return true;
        }

        return in_array($identifier, $identifiers, true);
    }

    // ==================== Categories Settings ====================

    /**
     * Check if category sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCategorySyncEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_CATEGORIES_ENABLED, $storeId);
    }

    /**
     * Get minimum category level to sync
     *
     * @param int|null $storeId
     * @return int
     */
    public function getCategoryMinLevel(?int $storeId = null): int
    {
        return (int)($this->getValue(self::XML_PATH_CATEGORIES_MIN_LEVEL, $storeId) ?: 2);
    }

    /**
     * Check if inactive categories should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeInactiveCategories(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CATEGORIES_INCLUDE_INACTIVE, $storeId);
    }

    /**
     * Check if product count should be included
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeCategoryProductCount(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_CATEGORIES_INCLUDE_PRODUCT_COUNT, $storeId);
    }

    // ==================== Promotions Settings ====================

    /**
     * Check if promotion sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPromotionSyncEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_PROMOTIONS_ENABLED, $storeId);
    }

    /**
     * Get promotion rule types to sync
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPromotionRuleTypes(?int $storeId = null): string
    {
        return (string)($this->getValue(self::XML_PATH_PROMOTIONS_RULE_TYPES, $storeId) ?: 'both');
    }

    /**
     * Check if cart rules should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCartRules(?int $storeId = null): bool
    {
        $types = $this->getPromotionRuleTypes($storeId);
        return in_array($types, ['cart', 'both'], true);
    }

    /**
     * Check if catalog rules should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSyncCatalogRules(?int $storeId = null): bool
    {
        $types = $this->getPromotionRuleTypes($storeId);
        return in_array($types, ['catalog', 'both'], true);
    }

    /**
     * Check if inactive promotions should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeInactivePromotions(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_PROMOTIONS_INCLUDE_INACTIVE, $storeId);
    }

    /**
     * Check if expired promotions should be synced
     *
     * @param int|null $storeId
     * @return bool
     */
    public function includeExpiredPromotions(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_PROMOTIONS_INCLUDE_EXPIRED, $storeId);
    }

    // ==================== Queue Settings ====================

    /**
     * Get queue batch size
     *
     * @return int
     */
    public function getQueueBatchSize(): int
    {
        return (int)($this->getValue(self::XML_PATH_QUEUE_BATCH_SIZE) ?: 50);
    }

    /**
     * Get max retry attempts
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return (int)($this->getValue(self::XML_PATH_QUEUE_MAX_RETRIES) ?: 3);
    }

    /**
     * Get retry delays in minutes
     *
     * @return array
     */
    public function getRetryDelays(): array
    {
        $value = (string)($this->getValue(self::XML_PATH_QUEUE_RETRY_DELAYS) ?: '5,15,60');
        return array_map('intval', $this->parseCommaSeparated($value));
    }

    /**
     * Get queue cleanup days
     *
     * @return int
     */
    public function getQueueCleanupDays(): int
    {
        return (int)($this->getValue(self::XML_PATH_QUEUE_CLEANUP_DAYS) ?: 7);
    }

    // ==================== Schedule Settings ====================

    /**
     * Get products full sync cron expression
     *
     * @return string
     */
    public function getProductsSyncCron(): string
    {
        return (string)($this->getValue(self::XML_PATH_SCHEDULE_PRODUCTS) ?: '0 2 * * *');
    }

    /**
     * Get CMS full sync cron expression
     *
     * @return string
     */
    public function getCmsSyncCron(): string
    {
        return (string)($this->getValue(self::XML_PATH_SCHEDULE_CMS) ?: '30 2 * * *');
    }

    /**
     * Get categories full sync cron expression
     *
     * @return string
     */
    public function getCategoriesSyncCron(): string
    {
        return (string)($this->getValue(self::XML_PATH_SCHEDULE_CATEGORIES) ?: '0 3 * * *');
    }

    /**
     * Get promotions full sync cron expression
     *
     * @return string
     */
    public function getPromotionsSyncCron(): string
    {
        return (string)($this->getValue(self::XML_PATH_SCHEDULE_PROMOTIONS) ?: '0 * * * *');
    }

    // ==================== Chat Widget Settings ====================

    /**
     * Check if chat widget is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isWidgetEnabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->getFlag(self::XML_PATH_WIDGET_ENABLED, $storeId);
    }

    /**
     * Get widget tenant slug
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetTenantSlug(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_WIDGET_TENANT_SLUG, $storeId);
    }

    /**
     * Get excluded page handles for widget
     *
     * @param int|null $storeId
     * @return array
     */
    public function getWidgetExcludePages(?int $storeId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_WIDGET_EXCLUDE_PAGES, $storeId);
        return $this->parseCommaSeparated($value);
    }

    /**
     * Check if customer context should be sent to widget
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isWidgetCustomerContextEnabled(?int $storeId = null): bool
    {
        return $this->getFlag(self::XML_PATH_WIDGET_CUSTOMER_CONTEXT, $storeId);
    }

    /**
     * Get widget script URL
     *
     * Derives the widget URL from the configured webhook URL base
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetScriptUrl(?int $storeId = null): string
    {
        $baseUrl = $this->getWidgetBaseUrl($storeId);
        return $baseUrl ? $baseUrl . self::WIDGET_SCRIPT_PATH : '';
    }

    /**
     * Get widget config endpoint URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetConfigUrl(?int $storeId = null): string
    {
        $baseUrl = $this->getWidgetBaseUrl($storeId);
        return $baseUrl ? $baseUrl . self::WIDGET_CONFIG_PATH : '';
    }

    /**
     * Get widget API endpoint URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetApiUrl(?int $storeId = null): string
    {
        $baseUrl = $this->getWidgetBaseUrl($storeId);
        return $baseUrl ? $baseUrl . self::WIDGET_API_PATH : '';
    }

    /**
     * Get widget base URL from webhook URL
     *
     * Extracts scheme://host from the webhook URL
     *
     * @param int|null $storeId
     * @return string
     */
    private function getWidgetBaseUrl(?int $storeId = null): string
    {
        $webhookUrl = $this->getWebhookUrl($storeId);
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

    // ==================== Helper Methods ====================

    /**
     * Parse comma-separated string into array
     *
     * @param string $value
     * @return array
     */
    private function parseCommaSeparated(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $value)),
            fn($item) => $item !== ''
        );
    }

    /**
     * Check if connection is configured
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isConnectionConfigured(?int $storeId = null): bool
    {
        return !empty($this->getWebhookUrl($storeId))
            && !empty($this->getApiSecret($storeId));
    }

    /**
     * Get all config as array (for debugging/dashboard)
     *
     * @param int|null $storeId
     * @return array
     */
    public function getAllConfig(?int $storeId = null): array
    {
        return [
            'enabled' => $this->isEnabled($storeId),
            'environment' => $this->getEnvironment(),
            'debug' => $this->isDebugEnabled(),
            'connection' => [
                'configured' => $this->isConnectionConfigured($storeId),
                'webhook_url' => $this->getWebhookUrl($storeId),
                'tenant_id' => $this->getTenantId($storeId),
                'timeout' => $this->getConnectionTimeout(),
            ],
            'products' => [
                'enabled' => $this->isProductSyncEnabled($storeId),
                'include_disabled' => $this->includeDisabledProducts($storeId),
                'sync_attributes' => $this->getProductSyncAttributes($storeId),
            ],
            'cms_pages' => [
                'enabled' => $this->isCmsPageSyncEnabled($storeId),
                'mode' => $this->getCmsPagesSyncMode($storeId),
            ],
            'cms_blocks' => [
                'enabled' => $this->isCmsBlockSyncEnabled($storeId),
            ],
            'categories' => [
                'enabled' => $this->isCategorySyncEnabled($storeId),
                'min_level' => $this->getCategoryMinLevel($storeId),
            ],
            'promotions' => [
                'enabled' => $this->isPromotionSyncEnabled($storeId),
                'rule_types' => $this->getPromotionRuleTypes($storeId),
            ],
            'queue' => [
                'batch_size' => $this->getQueueBatchSize(),
                'max_retries' => $this->getMaxRetries(),
                'retry_delays' => $this->getRetryDelays(),
            ],
        ];
    }
}
