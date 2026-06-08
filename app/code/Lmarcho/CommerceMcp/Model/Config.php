<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_ENABLED = 'commerce_mcp/general/enabled';
    private const XML_PATH_MAX_REQUEST_BYTES = 'commerce_mcp/general/max_request_bytes';
    private const XML_PATH_MAX_RESPONSE_BYTES = 'commerce_mcp/general/max_response_bytes';
    private const XML_PATH_ALLOWED_STORE_CODES = 'commerce_mcp/general/allowed_store_codes';
    private const XML_PATH_MAX_SKUS_PER_REQUEST = 'commerce_mcp/general/max_skus_per_request';
    private const XML_PATH_MAX_GALLERY_IMAGES = 'commerce_mcp/general/max_gallery_images';
    private const XML_PATH_MAX_VARIANTS_PER_PRODUCT = 'commerce_mcp/general/max_variants_per_product';
    private const XML_PATH_VARIANT_IMAGE_FALLBACK = 'commerce_mcp/general/variant_image_fallback_enabled';
    private const XML_PATH_MAX_SEARCH_RESULTS = 'commerce_mcp/general/max_search_results';
    private const XML_PATH_MAX_RELATED_PRODUCTS = 'commerce_mcp/general/max_related_products';
    private const XML_PATH_MAX_PROMOTIONS = 'commerce_mcp/general/max_promotions';
    private const XML_PATH_PUBLIC_COUPON_CODES = 'commerce_mcp/general/public_coupon_codes';
    private const XML_PATH_ASSERTION_LIFETIME = 'commerce_mcp/general/customer_assertion_lifetime_seconds';
    private const XML_PATH_ASSERTION_SIGNING_KEY = 'commerce_mcp/general/customer_assertion_signing_key';
    private const XML_PATH_RATE_LIMIT_PER_MINUTE = 'commerce_mcp/general/rate_limit_per_minute';
    private const XML_PATH_ORDER_STATUS_RATE_LIMIT_PER_MINUTE = 'commerce_mcp/general/order_status_rate_limit_per_minute';
    private const XML_PATH_TRACKING_URL_TEMPLATES = 'commerce_mcp/general/tracking_url_templates';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    public function getMaxRequestBytes(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_REQUEST_BYTES));
    }

    public function getMaxResponseBytes(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_RESPONSE_BYTES));
    }

    /**
     * @return string[]
     */
    public function getAllowedStoreCodes(): array
    {
        $configured = (string)$this->scopeConfig->getValue(self::XML_PATH_ALLOWED_STORE_CODES);
        $codes = preg_split('/\s*,\s*/', trim($configured), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = array_filter($codes, static function (string $code): bool {
            return $code !== 'admin' && preg_match('/\A[a-zA-Z0-9_]+\z/', $code) === 1;
        });

        return array_values(array_unique($codes));
    }

    public function getMaxSkusPerRequest(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_SKUS_PER_REQUEST));
    }

    public function getMaxGalleryImages(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_GALLERY_IMAGES));
    }

    public function getMaxVariantsPerProduct(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_VARIANTS_PER_PRODUCT));
    }

    public function isVariantImageFallbackEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_VARIANT_IMAGE_FALLBACK);
    }

    public function getMaxSearchResults(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_SEARCH_RESULTS));
    }

    public function getMaxRelatedProducts(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_RELATED_PRODUCTS));
    }

    public function getMaxPromotions(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_PROMOTIONS));
    }

    /**
     * @return string[]
     */
    public function getPublicCouponCodes(): array
    {
        $configured = (string)$this->scopeConfig->getValue(self::XML_PATH_PUBLIC_COUPON_CODES);
        $codes = preg_split('/\s*,\s*/', trim($configured), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $codes = array_filter($codes, static function (string $code): bool {
            return $code !== '' && strlen($code) <= 64;
        });

        return array_values(array_unique(array_map('strtoupper', $codes)));
    }

    public function getCustomerAssertionLifetimeSeconds(): int
    {
        $lifetime = (int)$this->scopeConfig->getValue(self::XML_PATH_ASSERTION_LIFETIME);

        return min(300, max(60, $lifetime));
    }

    public function getCustomerAssertionSigningKey(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_ASSERTION_SIGNING_KEY));
    }

    public function getRateLimitPerMinute(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_RATE_LIMIT_PER_MINUTE));
    }

    public function getOrderStatusRateLimitPerMinute(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_ORDER_STATUS_RATE_LIMIT_PER_MINUTE));
    }

    /**
     * @return array<string,string>
     */
    public function getTrackingUrlTemplates(): array
    {
        $configured = trim((string)$this->scopeConfig->getValue(self::XML_PATH_TRACKING_URL_TEMPLATES));
        if ($configured === '') {
            return [];
        }
        $templates = [];
        foreach (preg_split('/\R+/', $configured, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            [$carrier, $template] = array_pad(explode('=', $line, 2), 2, '');
            $carrier = strtolower(trim($carrier));
            $template = trim($template);
            if ($carrier === '' || !str_contains($template, '{tracking_number}')) {
                continue;
            }
            $host = parse_url($template, PHP_URL_HOST);
            if (!is_string($host) || $host === '' || parse_url($template, PHP_URL_SCHEME) !== 'https') {
                continue;
            }
            $templates[$carrier] = $template;
        }

        return $templates;
    }
}
