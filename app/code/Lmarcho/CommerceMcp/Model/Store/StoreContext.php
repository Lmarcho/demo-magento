<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Store;

use Lmarcho\CommerceMcp\Api\Data\StoreContextInterface;

class StoreContext implements StoreContextInterface
{
    public function __construct(
        private readonly int $storeId,
        private readonly string $storeCode,
        private readonly string $storeName,
        private readonly int $websiteId,
        private readonly string $websiteCode,
        private readonly string $currency,
        private readonly string $locale,
        private readonly string $timezone,
        private readonly string $secureBaseUrl,
        private readonly string $secureMediaBaseUrl,
        private readonly string $salesChannelType,
        private readonly string $salesChannelCode,
        private readonly int $stockId
    ) {
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getStoreCode(): string
    {
        return $this->storeCode;
    }

    public function getStoreName(): string
    {
        return $this->storeName;
    }

    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    public function getWebsiteCode(): string
    {
        return $this->websiteCode;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getSecureBaseUrl(): string
    {
        return $this->secureBaseUrl;
    }

    public function getSecureMediaBaseUrl(): string
    {
        return $this->secureMediaBaseUrl;
    }

    public function getSalesChannelType(): string
    {
        return $this->salesChannelType;
    }

    public function getSalesChannelCode(): string
    {
        return $this->salesChannelCode;
    }

    public function getStockId(): int
    {
        return $this->stockId;
    }

    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'store_code' => $this->storeCode,
            'store_name' => $this->storeName,
            'website_id' => $this->websiteId,
            'website_code' => $this->websiteCode,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'secure_base_url' => $this->secureBaseUrl,
            'secure_media_base_url' => $this->secureMediaBaseUrl,
            'sales_channel' => [
                'type' => $this->salesChannelType,
                'code' => $this->salesChannelCode,
            ],
            'stock_id' => $this->stockId,
        ];
    }
}
