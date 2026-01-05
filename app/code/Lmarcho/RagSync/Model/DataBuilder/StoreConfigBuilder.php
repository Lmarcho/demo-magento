<?php
/**
 * Lmarcho RagSync Module - Store Config Builder
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\DataBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\CountryFactory;
use Lmarcho\RagSync\Model\Config;

class StoreConfigBuilder
{
    private const XML_PATH_STORE_NAME = 'general/store_information/name';
    private const XML_PATH_STORE_PHONE = 'general/store_information/phone';
    private const XML_PATH_STORE_ADDRESS = 'general/store_information/street_line1';
    private const XML_PATH_STORE_ADDRESS2 = 'general/store_information/street_line2';
    private const XML_PATH_STORE_CITY = 'general/store_information/city';
    private const XML_PATH_STORE_REGION = 'general/store_information/region_id';
    private const XML_PATH_STORE_POSTCODE = 'general/store_information/postcode';
    private const XML_PATH_STORE_COUNTRY = 'general/store_information/country_id';
    private const XML_PATH_STORE_HOURS = 'general/store_information/hours';
    private const XML_PATH_TRANS_EMAIL_GENERAL = 'trans_email/ident_general/email';
    private const XML_PATH_TRANS_EMAIL_SUPPORT = 'trans_email/ident_support/email';
    private const XML_PATH_LOCALE = 'general/locale/code';
    private const XML_PATH_TIMEZONE = 'general/locale/timezone';
    private const XML_PATH_WEIGHT_UNIT = 'general/locale/weight_unit';
    private const XML_PATH_ALLOWED_COUNTRIES = 'general/country/allow';
    private const XML_PATH_DEFAULT_COUNTRY = 'general/country/default';
    private const XML_PATH_BASE_CURRENCY = 'currency/options/base';
    private const XML_PATH_DEFAULT_CURRENCY = 'currency/options/default';
    private const XML_PATH_ALLOWED_CURRENCIES = 'currency/options/allow';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CurrencyFactory
     */
    private CurrencyFactory $currencyFactory;

    /**
     * @var CountryFactory
     */
    private CountryFactory $countryFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param CountryFactory $countryFactory
     * @param Config $config
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        CountryFactory $countryFactory,
        Config $config
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->countryFactory = $countryFactory;
        $this->config = $config;
    }

    /**
     * Build store configuration data for webhook
     *
     * @param int|null $storeId
     * @return array
     */
    public function build(?int $storeId = null): array
    {
        try {
            $store = $storeId !== null
                ? $this->storeManager->getStore($storeId)
                : $this->storeManager->getStore();

            $currencyCode = $store->getCurrentCurrencyCode();
            $baseCurrencyCode = $store->getBaseCurrencyCode();

            return [
                'type' => 'store_config',
                'store_id' => (int)$store->getId(),
                'store_code' => $store->getCode(),
                'website_id' => (int)$store->getWebsiteId(),
                'currency_code' => $currencyCode,
                'currency_symbol' => $this->getCurrencySymbol($currencyCode),
                'base_currency_code' => $baseCurrencyCode,
                'base_currency_symbol' => $this->getCurrencySymbol($baseCurrencyCode),
                'allowed_currencies' => $this->getAllowedCurrencies($storeId),
                'store_name' => $this->getConfig(self::XML_PATH_STORE_NAME, $storeId) ?: $store->getName(),
                'store_email' => $this->getConfig(self::XML_PATH_TRANS_EMAIL_GENERAL, $storeId),
                'store_phone' => $this->getConfig(self::XML_PATH_STORE_PHONE, $storeId),
                'support_email' => $this->getConfig(self::XML_PATH_TRANS_EMAIL_SUPPORT, $storeId),
                'store_address' => $this->buildStoreAddress($storeId),
                'locale' => $this->getConfig(self::XML_PATH_LOCALE, $storeId),
                'timezone' => $this->getConfig(self::XML_PATH_TIMEZONE, $storeId),
                'weight_unit' => $this->getConfig(self::XML_PATH_WEIGHT_UNIT, $storeId),
                'business_hours' => $this->parseBusinessHours($storeId),
                'default_country' => $this->getConfig(self::XML_PATH_DEFAULT_COUNTRY, $storeId),
                'shipping_countries' => $this->getShippingCountries($storeId),
                'base_url' => $store->getBaseUrl(),
                'secure_base_url' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true),
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'store_config',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get config value for store scope
     *
     * @param string $path
     * @param int|null $storeId
     * @return string|null
     */
    private function getConfig(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get currency symbol
     *
     * @param string $currencyCode
     * @return string
     */
    private function getCurrencySymbol(string $currencyCode): string
    {
        try {
            $currency = $this->currencyFactory->create()->load($currencyCode);
            return $currency->getCurrencySymbol() ?: $currencyCode;
        } catch (\Exception $e) {
            return $currencyCode;
        }
    }

    /**
     * Get allowed currencies
     *
     * @param int|null $storeId
     * @return array
     */
    private function getAllowedCurrencies(?int $storeId = null): array
    {
        $currencies = $this->getConfig(self::XML_PATH_ALLOWED_CURRENCIES, $storeId);
        if (empty($currencies)) {
            return [];
        }

        $codes = explode(',', $currencies);
        $result = [];

        foreach ($codes as $code) {
            $code = trim($code);
            if (!empty($code)) {
                $result[] = [
                    'code' => $code,
                    'symbol' => $this->getCurrencySymbol($code),
                ];
            }
        }

        return $result;
    }

    /**
     * Build full store address string
     *
     * @param int|null $storeId
     * @return string|null
     */
    private function buildStoreAddress(?int $storeId = null): ?string
    {
        $parts = array_filter([
            $this->getConfig(self::XML_PATH_STORE_ADDRESS, $storeId),
            $this->getConfig(self::XML_PATH_STORE_ADDRESS2, $storeId),
            $this->getConfig(self::XML_PATH_STORE_CITY, $storeId),
            $this->getRegionName($storeId),
            $this->getConfig(self::XML_PATH_STORE_POSTCODE, $storeId),
            $this->getCountryName($storeId),
        ]);

        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Get region name from region ID
     *
     * @param int|null $storeId
     * @return string|null
     */
    private function getRegionName(?int $storeId = null): ?string
    {
        $regionId = $this->getConfig(self::XML_PATH_STORE_REGION, $storeId);
        if (empty($regionId)) {
            return null;
        }

        // Region ID is stored, return as-is for now
        // Could be expanded to load region name from directory
        return $regionId;
    }

    /**
     * Get country name from country code
     *
     * @param int|null $storeId
     * @return string|null
     */
    private function getCountryName(?int $storeId = null): ?string
    {
        $countryCode = $this->getConfig(self::XML_PATH_STORE_COUNTRY, $storeId);
        if (empty($countryCode)) {
            return null;
        }

        try {
            $country = $this->countryFactory->create()->loadByCode($countryCode);
            return $country->getName() ?: $countryCode;
        } catch (\Exception $e) {
            return $countryCode;
        }
    }

    /**
     * Parse business hours from config
     *
     * @param int|null $storeId
     * @return array|null
     */
    private function parseBusinessHours(?int $storeId = null): ?array
    {
        $hours = $this->getConfig(self::XML_PATH_STORE_HOURS, $storeId);
        if (empty($hours)) {
            return null;
        }

        // Try to decode as JSON first
        $decoded = json_decode($hours, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Otherwise return raw text for parsing
        return ['raw' => $hours];
    }

    /**
     * Get shipping countries list
     *
     * @param int|null $storeId
     * @return array
     */
    private function getShippingCountries(?int $storeId = null): array
    {
        $countries = $this->getConfig(self::XML_PATH_ALLOWED_COUNTRIES, $storeId);
        if (empty($countries)) {
            return [];
        }

        return array_map('trim', explode(',', $countries));
    }

    /**
     * Check if store config sync should run
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSync(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }
}
