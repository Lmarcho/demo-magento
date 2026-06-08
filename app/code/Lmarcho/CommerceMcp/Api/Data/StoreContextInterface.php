<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api\Data;

interface StoreContextInterface
{
    public function getStoreId(): int;

    public function getStoreCode(): string;

    public function getStoreName(): string;

    public function getWebsiteId(): int;

    public function getWebsiteCode(): string;

    public function getCurrency(): string;

    public function getLocale(): string;

    public function getTimezone(): string;

    public function getSecureBaseUrl(): string;

    public function getSecureMediaBaseUrl(): string;

    public function getSalesChannelType(): string;

    public function getSalesChannelCode(): string;

    public function getStockId(): int;

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array;
}
