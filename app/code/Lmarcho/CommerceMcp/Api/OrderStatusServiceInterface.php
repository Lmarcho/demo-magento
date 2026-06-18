<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface OrderStatusServiceInterface
{
    /**
     * @return array{order:array<string,mixed>}
     */
    public function get(string $storeCode, string $orderNumber, string $customerAssertion): array;

    /**
     * @return array{order:array<string,mixed>}
     */
    public function verifyGuest(string $storeCode, string $orderNumber, string $contact): array;
}
