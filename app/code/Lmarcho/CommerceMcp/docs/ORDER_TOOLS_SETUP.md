# MCP Order Tools Setup

This guide covers the Magento order tools used by the AskRAG widget.

## Required Versions

- Lmarcho_CommerceMcp module: 0.9.0 or later.
- Magento Open Source or Adobe Commerce compatible with Magento framework
  `>=103.0 <104`.
- PHP: 8.1, 8.2, or 8.3.
- AskRAG backend: must support the widget order verification endpoint and the
  `commerce:mcp:health` command with `verify_guest_order`.

## Tools

The module exposes these order tools when the MCP client role allows them:

- `get_order_status`: for logged-in customers. Requires a short-lived Magento
  customer assertion issued by this Magento store.
- `verify_guest_order`: for guest checkout. Requires store code, order number,
  and the billing email or phone used on the order.

Both tools are read-only. Guest verification returns only order number, status,
placed date, currency, total, visible item count, and shipping method summary.

## Magento Installation

For Composer installation:

```bash
composer require lmarcho/module-commerce-mcp
php bin/magento module:enable Lmarcho_CommerceMcp
php bin/magento setup:upgrade
php bin/magento cache:clean config full_page
```

Production mode deployments must also run:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
```

For local `app/code` development, place the module at:

```text
app/code/Lmarcho/CommerceMcp
```

Then run the same Magento commands above.

## MCP Client Setup

Create a named read-only client and copy the displayed token once:

```bash
php bin/magento commerce-mcp:client:create "AskRAG Laravel"
```

Optional maintenance commands:

```bash
php bin/magento commerce-mcp:tools:list
php bin/magento commerce-mcp:token:rotate "AskRAG Laravel"
php bin/magento commerce-mcp:client:revoke "AskRAG Laravel"
```

Configure the AskRAG tenant Magento settings with:

- MCP endpoint: `https://STORE-DOMAIN/commerce-mcp`
- MCP token: the generated `lmcp_...` token
- Store code: usually `default`, unless the storefront uses another store view
- Driver mode: `shadow` for rollout, then `mcp` after verification

## Upgrade Notes

Run `setup:upgrade` after every module update. Data patches backfill new
approved read-only tools, including `verify_guest_order`, into the default
`Lmarcho Chat Read Only` role.

If a role was manually customized to exclude tools, update the role or create a
new MCP client.

## Health Check

From the AskRAG backend, run:

```bash
php artisan commerce:mcp:health TENANT_SLUG --platform=magento --json
```

The health check should report:

- `required_tools_present: true`
- no missing tools
- `get_order_status` in the tool list
- `verify_guest_order` in the tool list
- valid store context for the configured store code

## Widget Behavior

- Logged-in Magento customers asking about an order get a same-origin customer
  assertion from `/commerce-mcp/customer/assertion`. AskRAG then calls
  `get_order_status`.
- Guests asking about order status see a compact verification form in the widget.
  The form asks for order number and billing email or phone.
- The billing email or phone is posted to the AskRAG verification endpoint, not
  sent through normal chat text and not included in the LLM prompt.
- Failed or mismatched checks return a generic message so order numbers cannot
  be enumerated.
- Product questions that contain similar words, such as "track pants", continue
  through normal product chat.

## Manual Test Cases

1. Logged-in customer asks: "Where is order 000000123?"
2. Guest asks: "Track order 000000123" and submits the correct billing email.
3. Guest submits the correct order number with the wrong email.
4. Guest submits the correct order number with the correct billing phone.
5. Guest submits an order number from another store view.
6. Visitor asks a product question such as "Show me track pants."
