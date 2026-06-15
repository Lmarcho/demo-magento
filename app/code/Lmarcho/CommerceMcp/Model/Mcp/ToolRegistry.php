<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

use Lmarcho\CommerceMcp\Api\ToolInterface;

class ToolRegistry
{
    private const TOOLS = [
        'get_store_context' => 'Return the resolved public Magento store context.',
        'get_products_live' => 'Return normalized live commerce data for requested SKUs.',
        'search_products_live' => 'Search public storefront products and return normalized results.',
        'get_category_products' => 'Return public storefront products assigned to a category.',
        'get_product_variants' => 'Return bounded configurable-product variant data.',
        'get_related_products' => 'Return related, upsell, or cross-sell products.',
        'get_active_promotions' => 'Return public active promotion summaries.',
        'get_product_popularity' => 'Return aggregate product purchase counts for ranking.',
        'get_order_status' => 'Return a customer-owned order status using a Magento assertion.',
        'get_customer_cart' => 'Return the asserted customer active cart.',
        'get_customer_purchase_history' => 'Return asserted customer product-level purchase history.',
    ];

    /**
     * @param array<string,ToolInterface> $implementedTools
     */
    public function __construct(private readonly array $implementedTools = [])
    {
    }

    /**
     * @param string[] $allowedTools
     * @return array<int,array<string,mixed>>
     */
    public function list(array $allowedTools): array
    {
        $tools = [];
        foreach (self::TOOLS as $name => $description) {
            if (!in_array($name, $allowedTools, true)) {
                continue;
            }
            $tool = $this->implementedTools[$name] ?? null;
            $tools[] = [
                'name' => $name,
                'description' => $tool?->getDescription() ?? $description,
                'inputSchema' => $tool?->getInputSchema() ?? [
                    'type' => 'object',
                    'additionalProperties' => false,
                ],
            ];
        }

        return $tools;
    }

    public function exists(string $name): bool
    {
        return isset(self::TOOLS[$name]);
    }

    public function getImplemented(string $name): ?ToolInterface
    {
        return $this->implementedTools[$name] ?? null;
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys(self::TOOLS);
    }
}
