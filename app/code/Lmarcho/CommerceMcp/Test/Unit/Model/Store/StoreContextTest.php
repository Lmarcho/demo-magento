<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Store;

use Lmarcho\CommerceMcp\Model\Store\StoreContext;
use PHPUnit\Framework\TestCase;

class StoreContextTest extends TestCase
{
    public function testSerializesNormalizedContext(): void
    {
        $context = new StoreContext(
            1,
            'default',
            'Default Store View',
            1,
            'base',
            'USD',
            'en_US',
            'America/Los_Angeles',
            'https://store.example/',
            'https://cdn.example/media/',
            'website',
            'base',
            1
        );

        self::assertSame([
            'store_id' => 1,
            'store_code' => 'default',
            'store_name' => 'Default Store View',
            'website_id' => 1,
            'website_code' => 'base',
            'currency' => 'USD',
            'locale' => 'en_US',
            'timezone' => 'America/Los_Angeles',
            'secure_base_url' => 'https://store.example/',
            'secure_media_base_url' => 'https://cdn.example/media/',
            'sales_channel' => [
                'type' => 'website',
                'code' => 'base',
            ],
            'stock_id' => 1,
        ], $context->toArray());
    }
}
