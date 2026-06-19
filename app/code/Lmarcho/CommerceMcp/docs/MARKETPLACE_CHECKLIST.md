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
- Primary distribution target is Adobe Commerce Marketplace / `repo.magento.com`,
  not Packagist.
- Keep the Composer `version` field in Marketplace ZIP releases. The package
  artifact is uploaded as a fixed release, and the ZIP filename, `composer.json`
  version, server `serverInfo.version`, setup docs, and release notes must all
  match.
- If the same package is later published to Packagist from a VCS repository,
  remove the Composer `version` field on that branch and use Git tags as the
  source of truth.
- The current license policy is commercial/proprietary. Keep
  `"license": "proprietary"` unless legal approves an open-source license.
- The Marketplace listing license/EULA must match the Composer `license` value
  or a documented custom license in the Marketplace portal.
- Do not add a placeholder open-source `LICENSE` file. If legal approves an
  open-source release, change Composer `license` to the approved SPDX identifier
  and add the matching `LICENSE` file in the same release.

## Documentation Required for Review

Commerce Marketplace technical review expects installation and usage
documentation. Use these files as the source for the PDF documentation uploaded
in the Developer Portal:

- `docs/COMMERCE_MCP_USER_GUIDE.pdf`: customer-facing user guide for Marketplace
  manual QA upload.
- `docs/COMMERCE_MCP_USER_GUIDE.html`: editable source for the PDF user guide.
- `README.md`: module summary, installation, client commands, and tests.
- `docs/ORDER_TOOLS_SETUP.md`: order status and guest verification setup.
- `docs/IMPLEMENTATION_NOTES.md`: implementation decisions and security notes.

The customer-facing user guide PDF must remain under the Marketplace size limit
and include:

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
- `docs/COMMERCE_MCP_USER_GUIDE.pdf`
- customer-facing docs and operational setup notes

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
