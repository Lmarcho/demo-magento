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
