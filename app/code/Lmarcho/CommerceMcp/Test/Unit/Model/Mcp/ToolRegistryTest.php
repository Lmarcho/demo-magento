<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Mcp;

use Lmarcho\CommerceMcp\Model\Mcp\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    public function testRegistryContainsOnlyApprovedTools(): void
    {
        self::assertSame([
            'get_store_context',
            'get_products_live',
            'search_products_live',
            'get_category_products',
            'get_product_variants',
            'get_related_products',
            'get_active_promotions',
            'get_product_popularity',
            'get_order_status',
            'verify_guest_order',
            'get_customer_cart',
            'get_customer_purchase_history',
        ], (new ToolRegistry())->names());
    }

    public function testListIsFilteredByRoleTools(): void
    {
        $tools = (new ToolRegistry())->list(['get_store_context']);

        self::assertCount(1, $tools);
        self::assertSame('get_store_context', $tools[0]['name']);
    }
}
