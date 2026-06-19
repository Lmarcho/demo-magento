# Adobe Commerce Marketplace Checklist

Use this checklist before submitting `Lmarcho_CommerceMcp` to Commerce
Marketplace.

## Package Metadata

- `composer.json` is at the module root.
- Composer package name uses lowercase vendor/package format:
  `lmarcho/module-commerce-mcp`.
- Composer type is `magento2-module`.
- Composer autoload includes `registration.php` and PSR-4 mapping for
  `Lmarcho\\CommerceMcp\\`.
- `registration.php` registers `Lmarcho_CommerceMcp`.
- `etc/module.xml` declares required Magento module sequence dependencies.
- The Marketplace listing license must match the Composer `license` value or a
  documented custom license in the Marketplace portal.

## Documentation Required for Review

Commerce Marketplace technical review expects installation and usage
documentation. Use these files as the source for the PDF documentation uploaded
in the Developer Portal:

- `README.md`: module summary, installation, client commands, and tests.
- `docs/ORDER_TOOLS_SETUP.md`: order status and guest verification setup.
- `docs/IMPLEMENTATION_NOTES.md`: implementation decisions and security notes.

Prepare at least one customer-facing user guide PDF under the Marketplace size
limit. Include:

- installation commands
- admin configuration fields
- token creation and rotation
- AskRAG tenant configuration
- MCP health check command
- widget behavior for logged-in and guest order status
- privacy and security boundaries

## Technical Review Checks

Before packaging:

1. Run unit tests:

   ```bash
   vendor/bin/phpunit -c app/code/Lmarcho/CommerceMcp/Test/phpunit.xml
   ```

2. Install on a clean Magento instance.
3. Run:

   ```bash
   php bin/magento module:enable Lmarcho_CommerceMcp
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:clean
   ```

4. Create an MCP client token.
5. Enable `commerce_mcp/general/enabled` after allowed store codes and the MCP
   client are configured.
6. Run the AskRAG MCP health check for a Magento tenant.
7. Test product, promotion, popularity, logged-in order, and guest order tools.
8. Confirm logs do not contain access tokens, customer assertions, full order
   numbers, billing email, phone, addresses, or payment data.

## Packaging

Package from the module root, not from the full Magento installation.

Recommended ZIP name pattern:

```text
lmarcho-module-commerce-mcp-0.9.0.zip
```

The package must include:

- `composer.json`
- `registration.php`
- `etc/`
- `Api/`
- `Model/`
- `Controller/`
- `Console/`
- `Setup/`
- `view/`
- customer-facing docs

Exclude:

- `.git`
- generated Magento code
- `var/`
- local environment files
- logs
- IDE files

## Release Notes

For the Marketplace release notes, include:

- read-only MCP server for Magento commerce data
- public product, category, promotion, popularity, cart, purchase history, and
  order tools
- logged-in customer order status with Magento-issued assertion
- guest order verification with order number plus billing email or phone
- strict read-only role and token rotation commands
