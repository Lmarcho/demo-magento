<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface CustomerAssertionServiceInterface
{
    public function issue(int $customerId, int $storeId, int $websiteId): string;

    /**
     * @return array{customer_id:int,store_id:int,website_id:int,nonce:string,exp:int}
     */
    public function verify(string $assertion, int $storeId, int $websiteId): array;
}
