# Lmarcho Commerce MCP

Magento-native, authenticated, read-only MCP server for live commerce data.

## Current status

Phases M1 through M8 are implemented:

- `POST /commerce-mcp`
- JSON-RPC 2.0
- MCP protocol revision `2025-11-25`
- `initialize`, `notifications/initialized`, `ping`, `tools/list`, and
  fail-closed `tools/call`
- Hashed bearer tokens for named clients
- Explicit read-only role containing only approved tools
- Token creation, rotation, and revocation
- Request/response size limits and correlation IDs
- Store-code allow-listing
- Active-store and website resolution
- Store-scoped currency, locale, timezone, secure link URL, and media URL
- MSI website sales-channel and stock resolution
- Executable `get_store_context` with MCP `structuredContent`
- Batched, store-scoped public product loading
- Enabled and storefront-visible product filtering
- Magento product URL generation
- Primary image and bounded media gallery resolution
- Magento adjusted regular/final prices and discount metadata
- Bulk MSI salability resolution
- Executable `get_products_live` with partial per-SKU errors
- Configurable option metadata and bounded child variant loading
- Store-scoped child pricing, MSI availability, and media resolution
- Configurable child image fallback to the parent, controlled by configuration
- Optional `variants` section and variant limit in `get_products_live`
- Executable `get_product_variants` for configurable and simple products
- Candidate-SKU filtered product search
- Magento native storefront search fallback
- Executable `search_products_live` with normalized product hydration
- Category-scoped public product browsing through `get_category_products`
- Related, upsell, and cross-sell product link resolution
- Executable `get_related_products` with per-link limits and link positions
- Public active catalog-rule and cart-rule summaries
- Coupon-code disclosure only through the configured public coupon allow-list
- Executable `get_active_promotions` with optional SKU relevance filtering
- Same-origin customer assertion endpoint for logged-in Magento customers
- Customer-owned order status serialization without addresses, email, payment,
  invoices, credit memos, or internal comments
- Executable `get_order_status` with customer assertion proof
- Customer-owned active cart reading through `get_customer_cart`, with actual
  item SKU plus visible `product_sku` for storefront hydration
- Customer-owned product-level purchase history through
  `get_customer_purchase_history`, without order numbers, addresses, email, or
  payment data; hidden child variant SKUs map to their visible parent
  `product_sku` where possible
- Per-client and order-status-specific rate limiting
- Sanitized MCP request/response logging and timing metadata
- Configured HTTPS tracking URL templates by carrier code

Ten approved commerce tool handlers are implemented.

## Installation

```bash
php bin/magento module:enable Lmarcho_CommerceMcp
php bin/magento setup:upgrade
php bin/magento cache:clean config full_page
```

Production deployments must also run:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
```

## Client commands

Create a client and display its token once:

```bash
php bin/magento commerce-mcp:client:create "Laravel Chat"
```

Optionally set a UTC expiry:

```bash
php bin/magento commerce-mcp:client:create "Laravel Chat" \
  --expires-at="2026-12-31 23:59:59"
```

Rotate or revoke:

```bash
php bin/magento commerce-mcp:token:rotate "Laravel Chat"
php bin/magento commerce-mcp:client:revoke "Laravel Chat"
```

List the approved registry:

```bash
php bin/magento commerce-mcp:tools:list
```

## Example initialize request

```bash
curl -X POST https://store.example/commerce-mcp \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  --data '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-11-25",
      "capabilities": {},
      "clientInfo": {"name": "laravel", "version": "1.0"}
    }
  }'
```

## Tests

```bash
vendor/bin/phpunit -c app/code/Lmarcho/CommerceMcp/Test/phpunit.xml
```
