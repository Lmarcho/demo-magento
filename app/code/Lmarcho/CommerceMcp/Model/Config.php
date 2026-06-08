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
}
