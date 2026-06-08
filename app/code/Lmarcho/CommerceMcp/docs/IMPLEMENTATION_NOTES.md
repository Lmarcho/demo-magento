# Commerce MCP Implementation Notes

## Baseline recorded on 2026-06-08

- Magento Open Source: 2.4.7
- PHP CLI: 8.3.22
- Primary MCP protocol revision: `2025-11-25`
- The draft `2026-07-28` revision is not enabled because it is a release
  candidate and its final publication date is after this implementation date.

## Reference review

### Freento/Magento-2-Mcp

- Repository: https://github.com/Freento/Magento-2-Mcp
- Reviewed branch: `main`
- License: MIT
- Useful concepts: Magento-native module registration, HTTP controller,
  JSON-RPC routing, tool registry injection, hashed bearer tokens, client roles,
  and admin configuration.
- Rejected concepts: broad entity tools, unrestricted filters and analytics,
  customer/admin access, raw order/quote/credit memo access, and a hardcoded
  protocol implementation without this project's contract controls.

### boldcommerce/magento2-mcp

- Repository: https://github.com/boldcommerce/magento2-mcp
- Reviewed branch: `main`
- License file: GPL-3.0. The package metadata currently says ISC, so the more
  restrictive repository license file is treated as authoritative.
- Useful concepts only: product/stock/related-product REST interactions and MCP
  SDK tool declaration patterns.
- No source code is copied. The stdio architecture, broad read/write tool set,
  customer lookup by email, analytics tools, and disabled TLS verification do
  not satisfy this module's security boundary.

## Phase M1 decisions

- The endpoint is available through Magento's `commerce-mcp` frontend route.
- Requests use JSON-RPC 2.0 and normal calls return `application/json`.
- The server is stateless and does not issue an MCP session ID.
- Only `2025-11-25` is accepted initially. Older revisions can be added only
  after a tested client requirement is documented.
- Exactly seven approved tool names are discoverable. Tool calls fail closed
  until their implementation phase is complete.
- Authentication is required before deployment; the protocol classes are kept
  independent so they can be unit tested without Magento database fixtures.

## Phase M2 decisions

- Every commerce request identifies a Magento store by `store_code`.
- Allowed store codes are controlled by
  `commerce_mcp/general/allowed_store_codes`; the default local value is
  `default`.
- The `admin` store is always removed from the allow-list.
- Store resolution requires an active Magento storefront and never accepts
  website IDs or stock IDs from the MCP client.
- Currency, locale, timezone, link URL, and media URL are resolved in store
  scope.
- URL values come from Magento store URL services with the secure flag. This
  local installation currently configures its secure base URL as
  `http://magento.test/`; production must configure HTTPS.
- MSI stock is resolved from the website sales channel through
  `StockResolverInterface`.
- The normalized tool response uses `schema_version: 1.0`, text content, and
  MCP `structuredContent`.

## Phase M3 decisions

- `get_products_live` accepts a store code, ordered SKU candidates, optional
  sections, and a bounded gallery limit.
- SKU candidates are validated, deduplicated, and limited before catalog work.
- Products are loaded in one store-scoped collection query and retain candidate
  order in the response.
- Disabled and not-visible-individually products are excluded from this
  customer-facing tool.
- Product URLs come from Magento URL services with the secure flag.
- Media gallery data is attached to the collection in bulk. Image paths use
  Magento's media-path service and the requested store's secure media base URL.
- Regular and final values use Magento adjusted pricing amounts. Discount
  amount and percentage are derived only when regular price exceeds final
  price.
- Salability uses one `AreProductsSalableInterface` call for all loaded SKUs
  and the stock ID resolved during Phase M2. Inventory failures return
  `UNKNOWN` rather than physical source quantities.
- Missing or excluded SKUs return bounded `PRODUCT_NOT_AVAILABLE` partial
  errors without failing successful products.
- The current Magento store is restored in a `finally` block after requested
  store emulation.

## Phase M4 decisions

- `get_product_variants` accepts a store code, parent SKU, and optional bounded
  variant limit. Simple products return empty option and variant collections.
- Configurable option metadata comes from Magento's configurable attribute
  model and remains complete when the returned child collection is truncated.
- Enabled child products are loaded in store scope with deterministic entity ID
  ordering. The response reports total, returned, and truncated counts.
- Child option values include attribute codes, frontend labels, raw values, and
  store-scoped option labels.
- Child prices use the same Magento adjusted-price resolver as product
  hydration. Availability is resolved in one bulk MSI request for the requested
  store's website stock.
- Variant media prefers the child primary image. When
  `commerce_mcp/product/variant_image_fallback_enabled` is enabled, a missing
  child image falls back to the parent primary image and is explicitly marked.
- `get_products_live` can include a `variants` section with its own bounded
  limit. The server maximum is configured by
  `commerce_mcp/product/max_variants_per_product`.
- The local sample catalog verifies configurable options, truncation, child
  availability, and child media. Its configurable children have equal prices,
  so ranged configurable pricing is implemented through Magento's pricing
  model but could not be live-verified against a varied-price fixture.
