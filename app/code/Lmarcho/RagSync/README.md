# Lmarcho RagSync Module for Magento 2

A robust Magento 2 module that synchronizes catalog and CMS content to a RAG (Retrieval-Augmented Generation) backend for AI-powered search and chat applications.

## Features

- **Queue-Based Sync**: Non-blocking, asynchronous synchronization that never slows down admin operations
- **Multi-Entity Support**: Products, CMS Pages, CMS Blocks, Categories, and Promotions
- **Smart Deduplication**: Multiple saves result in a single sync operation
- **Circuit Breaker**: Automatic failover when the backend is unavailable
- **Configurable Retry**: Exponential backoff with configurable retry attempts
- **Admin Dashboard**: Real-time monitoring of sync status and queue health
- **CLI Commands**: Full command-line interface for operations and troubleshooting
- **Document Type Detection**: Automatic classification of CMS pages (policy, FAQ, guide, etc.)

## Requirements

- Magento 2.4.0 or higher
- PHP 8.1 or higher
- A RAG backend with compatible webhook endpoints

## Installation

### Via Composer (Recommended)

```bash
composer require lmarcho/module-ragsync
bin/magento module:enable Lmarcho_RagSync
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Manual Installation

1. Create the directory structure:
   ```bash
   mkdir -p app/code/Lmarcho/RagSync
   ```

2. Copy module files to `app/code/Lmarcho/RagSync/`

3. Enable the module:
   ```bash
   bin/magento module:enable Lmarcho_RagSync
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:clean
   ```

## Configuration

Navigate to **Stores → Configuration → Services → RAG Sync Integration**

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Module | Master switch for the module | No |
| Environment | Production, Staging, or Development | Production |
| Debug Mode | Enable verbose logging | No |

### Connection Settings

| Setting | Description |
|---------|-------------|
| Webhook Base URL | Your RAG backend URL (e.g., `https://rag.example.com/api/webhooks/magento`) |
| Tenant ID | Your unique tenant identifier |
| API Secret Key | Shared secret for HMAC signature verification |
| Connection Timeout | HTTP request timeout in seconds (default: 30) |

### Entity Settings

Configure which entities to sync and their specific options:

- **Products**: Enable/disable, include disabled products, sync attributes
- **CMS Pages**: Whitelist/blacklist mode, page identifiers
- **CMS Blocks**: Enable/disable, block identifiers
- **Categories**: Minimum level, include inactive
- **Promotions**: Cart rules, catalog rules, or both

### Queue Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Batch Size | Items per webhook request | 50 |
| Max Retries | Attempts before marking dead | 3 |
| Retry Delays | Minutes between retries | 5,15,60 |
| Cleanup Days | Days to keep sent items | 7 |

## Usage

### Admin Dashboard

Access via **Marketing → RAG Sync → Dashboard**

The dashboard provides:
- Connection status and health
- Queue statistics (pending, processing, sent, failed, dead)
- Entity sync status for each type
- Quick action buttons:
  - **Test Connection**: Verify backend connectivity
  - **Process Queue**: Manually trigger queue processing
  - **Retry Failed**: Reset failed items to pending
  - **Clear Sent**: Remove successfully sent items

### Queue Grid

Access via **Marketing → RAG Sync → Queue** or Dashboard → View Queue

Features:
- Filterable by status, entity type, action
- Sortable by all columns
- Mass actions: Retry, Delete
- Real-time status updates

### CLI Commands

#### Sync Commands

```bash
# Sync all products
bin/magento ragsync:sync:products

# Sync specific products
bin/magento ragsync:sync:products --ids=123,456,789

# Sync products for specific store
bin/magento ragsync:sync:products --store=2

# Sync CMS content
bin/magento ragsync:sync:cms

# Sync only CMS pages
bin/magento ragsync:sync:cms --type=pages

# Sync only CMS blocks
bin/magento ragsync:sync:cms --type=blocks

# Sync categories
bin/magento ragsync:sync:categories

# Sync specific categories
bin/magento ragsync:sync:categories --ids=10,20,30

# Sync promotions
bin/magento ragsync:sync:promotions

# Sync only cart rules
bin/magento ragsync:sync:promotions --type=cart

# Sync only catalog rules
bin/magento ragsync:sync:promotions --type=catalog
```

#### Queue Commands

```bash
# Process pending queue items
bin/magento ragsync:queue:process

# View queue status
bin/magento ragsync:queue:status

# Clear sent items
bin/magento ragsync:queue:clear --status=sent

# Clear failed items
bin/magento ragsync:queue:clear --status=failed

# Clear all items (with confirmation)
bin/magento ragsync:queue:clear --status=all

# Clear without confirmation
bin/magento ragsync:queue:clear --status=sent --force
```

#### Utility Commands

```bash
# Test backend connection
bin/magento ragsync:test:connection
```

## Webhook Payload Format

### Single Entity

```json
{
  "type": "product",
  "id": "123",
  "action": "save",
  "store_id": 1,
  "timestamp": "2026-01-02T10:30:00Z",
  "data": {
    "sku": "PRODUCT-SKU",
    "name": "Product Name",
    "description": "Product description...",
    "categories": ["Electronics", "Phones"],
    "attributes": {
      "brand": "Apple",
      "color": "Black"
    }
  }
}
```

### Batch Request

```json
{
  "type": "batch",
  "batch_id": "mag-20260102103000-abcd1234",
  "timestamp": "2026-01-02T10:30:00Z",
  "items": [
    { "type": "product", "id": "1", "action": "save", "store_id": 1, "data": {...} },
    { "type": "product", "id": "2", "action": "save", "store_id": 1, "data": {...} }
  ]
}
```

### Headers

| Header | Description |
|--------|-------------|
| `X-Magento-Webhook-Signature` | HMAC-SHA256 signature (`sha256=<hex>`) |
| `X-Environment` | Environment identifier (`production` or `staging`) |
| `Content-Type` | `application/json` |
| `User-Agent` | `Magento-RagSync/1.0` |

## Queue States

```
pending → processing → sent
              ↓
           failed → pending (retry)
              ↓
            dead (max retries exceeded)
```

| Status | Description |
|--------|-------------|
| `pending` | Waiting to be processed |
| `processing` | Currently being sent |
| `sent` | Successfully delivered |
| `failed` | Delivery failed, will retry |
| `dead` | Max retries exceeded |

## Cron Jobs

| Job | Schedule | Description |
|-----|----------|-------------|
| `ragsync_process_queue` | `* * * * *` | Process pending items |
| `ragsync_full_product_sync` | `0 2 * * *` | Daily full product sync |
| `ragsync_full_cms_sync` | `30 2 * * *` | Daily full CMS sync |
| `ragsync_full_category_sync` | `0 3 * * *` | Daily full category sync |
| `ragsync_promotion_sync` | `0 * * * *` | Hourly promotion sync |
| `ragsync_cleanup_queue` | `0 5 * * *` | Daily queue cleanup |
| `ragsync_reset_stuck_items` | `*/15 * * * *` | Reset stuck items |

## Logging

Logs are written to `var/log/ragsync.log`

Enable debug mode for verbose logging:
**Stores → Configuration → Services → RAG Sync → General → Debug Mode → Yes**

## Database Tables

| Table | Purpose |
|-------|---------|
| `rag_sync_queue` | Sync queue with deduplication |
| `rag_sync_circuit_breaker` | Circuit breaker state |
| `rag_sync_log` | Audit trail |

## Troubleshooting

### Items stuck in "processing" status

Run the reset command or wait for the cron job:
```bash
# The cron job runs every 15 minutes automatically
# Or manually reset via dashboard → Retry Failed
```

### Connection test fails

1. Verify the Webhook URL is correct and accessible
2. Check API Secret matches on both ends
3. Ensure firewall allows outbound HTTPS connections
4. Check `var/log/ragsync.log` for detailed errors

### Queue not processing

1. Verify cron is running: `bin/magento cron:run`
2. Check if module is enabled in configuration
3. Check if circuit breaker is open (5 consecutive failures)
4. Review logs for errors

### High queue depth

1. Check backend availability
2. Increase batch size if backend can handle it
3. Review failed items for common errors
4. Consider running manual queue process

## Testing

Run unit tests:
```bash
bin/magento dev:tests:run unit --filter="Lmarcho"
```

Run integration tests:
```bash
bin/magento dev:tests:run integration --filter="Lmarcho"
```

## Uninstallation

```bash
bin/magento module:disable Lmarcho_RagSync
bin/magento setup:upgrade
bin/magento setup:di:compile
rm -rf app/code/Lmarcho/RagSync
bin/magento cache:clean
```

To remove database tables:
```sql
DROP TABLE IF EXISTS rag_sync_queue;
DROP TABLE IF EXISTS rag_sync_circuit_breaker;
DROP TABLE IF EXISTS rag_sync_log;
```

## Support

- GitHub Issues: [Report bugs and feature requests]
- Documentation: See `MAGENTO_SYNC_PLAN.md` for architecture details

## License

Proprietary - All rights reserved.

## Version History

- **1.0.0** (2026-01-02): Initial release
  - Queue-based sync system
  - Support for Products, CMS Pages, CMS Blocks, Categories, Promotions
  - Admin dashboard and queue grid
  - CLI commands
  - Circuit breaker and retry mechanism
