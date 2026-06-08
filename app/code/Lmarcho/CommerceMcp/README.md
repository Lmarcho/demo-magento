# Lmarcho Commerce MCP

Magento-native, authenticated, read-only MCP server for live commerce data.

## Current status

Phases M1 and M2 are implemented:

- `POST /commerce-mcp`
- JSON-RPC 2.0
- MCP protocol revision `2025-11-25`
- `initialize`, `notifications/initialized`, `ping`, `tools/list`, and
  fail-closed `tools/call`
- Hashed bearer tokens for named clients
- Explicit read-only role containing only the seven approved tools
- Token creation, rotation, and revocation
- Request/response size limits and correlation IDs
- Store-code allow-listing
- Active-store and website resolution
- Store-scoped currency, locale, timezone, secure link URL, and media URL
- MSI website sales-channel and stock resolution
- Executable `get_store_context` with MCP `structuredContent`

The remaining six commerce tool handlers are intentionally unavailable until
their implementation phase is completed.

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
