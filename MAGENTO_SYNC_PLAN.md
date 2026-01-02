# Magento 2 ↔ RAG Sync Integration Plan

## Executive Summary

This document outlines the complete integration strategy between Magento 2 and the RAG (Retrieval-Augmented Generation) backend system. The goal is to keep the RAG knowledge base synchronized with Magento content so customers receive accurate, up-to-date answers.

### Key Principles

1. **Queue-Based, Not Real-Time HTTP** - Never block Magento admin saves
2. **Idempotent Operations** - Re-syncing the same entity is safe
3. **Graceful Degradation** - RAG backend unavailable shouldn't break Magento
4. **Audit Trail** - Track what synced, when, and any failures
5. **Minimal Magento Footprint** - Light module, no heavy dependencies

---

## Table of Contents

- [Implementation Progress](#implementation-progress) ← **Current Status**
1. [Data Entities](#1-data-entities)
2. [Sync Architecture](#2-sync-architecture)
3. [Queue Design](#3-queue-design)
4. [Magento Module Design](#4-magento-module-design)
5. [Laravel Backend Updates](#5-laravel-backend-updates)
6. [Security](#6-security)
7. [Error Handling & Retry](#7-error-handling--retry)
8. [Monitoring & Alerting](#8-monitoring--alerting)
9. [Performance Considerations](#9-performance-considerations)
10. [Implementation Phases](#10-implementation-phases)
11. [Testing Strategy](#11-testing-strategy)
12. [Deployment Checklist](#12-deployment-checklist)
13. [Rollback Plan](#13-rollback-plan)

---

## Implementation Progress

| Phase | Status | Completed Date | Notes |
|-------|--------|----------------|-------|
| 5A: Laravel Backend Preparation | ✅ Complete | 2026-01-02 | All models, jobs, endpoints, and commands created |
| 5B: Magento Module Core | ✅ Complete | 2026-01-02 | Module skeleton, queue, observers, cron jobs |
| 5C: Magento Admin UI | ✅ Complete | 2026-01-02 | Dashboard, queue grid, AJAX controllers |
| 5D: CLI Commands | ✅ Complete | 2026-01-02 | All 8 CLI commands implemented |
| 5E: Testing & Docs | ✅ Complete | 2026-01-02 | Unit tests, integration tests, documentation |

### Phase 5A Completion Summary

**New Models & Migrations:**
| File | Purpose |
|------|---------|
| `app/Models/MagentoCmsPage.php` | CMS pages with document type mapping |
| `app/Models/MagentoCmsBlock.php` | CMS blocks for reusable content |
| `app/Models/MagentoPromotion.php` | Cart/catalog price rules |
| 3 new migrations | Tables for cms_pages, cms_blocks, promotions |

**New Jobs (6 total):**
| Job | Purpose |
|-----|---------|
| `SyncMagentoCmsPagesJob` | Sync CMS pages from Magento |
| `ChunkCmsPageJob` | Chunk & embed CMS page content |
| `SyncMagentoCmsBlocksJob` | Sync CMS blocks from Magento |
| `ChunkCmsBlockJob` | Chunk & embed CMS block content |
| `SyncMagentoPromotionsJob` | Sync promotions from Magento |
| `ChunkPromotionJob` | Chunk & embed promotion content |

**New Webhook Endpoints (12 routes):**
```
GET  /api/webhooks/magento/{tenant}/status            # Health check
POST /api/webhooks/magento/{tenant}/batch             # Batch multiple entities
POST /api/webhooks/magento/{tenant}/cms-page          # CMS page save
POST /api/webhooks/magento/{tenant}/cms-page/delete   # CMS page delete
POST /api/webhooks/magento/{tenant}/cms-block         # CMS block save
POST /api/webhooks/magento/{tenant}/cms-block/delete  # CMS block delete
POST /api/webhooks/magento/{tenant}/promotion         # Promotion save
POST /api/webhooks/magento/{tenant}/promotion/delete  # Promotion delete
```

**New Artisan Commands:**
```bash
php artisan magento:sync:cms           # Sync CMS pages and blocks
php artisan magento:sync:promotions    # Sync promotions
php artisan magento:status             # Show sync status for all entities
```

**Key Features Implemented:**
- **Document Type Mapping**: CMS pages automatically classified (policy, faq, support, guide)
- **Batch Webhook**: Process multiple entities in single request
- **Queue-based**: All sync/chunk jobs go to `magento-sync` queue
- **Tenant Isolation**: All entities scoped to `tenant_id`

### Phase 5B Completion Summary

**Module Structure Created:**
| Directory | Files Created |
|-----------|---------------|
| `etc/` | `module.xml`, `di.xml`, `config.xml`, `events.xml`, `crontab.xml`, `acl.xml`, `db_schema.xml` |
| `etc/adminhtml/` | `system.xml`, `menu.xml`, `routes.xml` |
| `Model/` | `Queue.php`, `Config.php`, `WebhookSender.php`, `WebhookResponse.php`, `CircuitBreaker.php`, `QueueService.php` |
| `Model/ResourceModel/` | `Queue.php`, `Queue/Collection.php` |
| `Model/DataBuilder/` | `ProductBuilder.php`, `CmsPageBuilder.php`, `CmsBlockBuilder.php`, `CategoryBuilder.php`, `PromotionBuilder.php` |
| `Model/Config/Source/` | `Environment.php`, `SyncMode.php`, `RuleTypes.php` |
| `Observer/` | 11 observer classes for all entity types |
| `Cron/` | `ProcessQueue.php`, `FullProductSync.php`, `FullCmsSync.php`, `FullCategorySync.php`, `PromotionSync.php`, `CleanupQueue.php`, `ResetStuckItems.php` |
| `Http/` | `ClientFactory.php` |

**Database Tables (via db_schema.xml):**
- `rag_sync_queue` - Main queue table with deduplication
- `rag_sync_circuit_breaker` - Circuit breaker state storage
- `rag_sync_log` - Sync audit trail

**Key Features:**
- Queue-based async sync (never blocks admin saves)
- HMAC-SHA256 signature verification
- Circuit breaker pattern for resilience
- Priority-based queue processing
- Configurable retry with exponential backoff

### Phase 5C Completion Summary

**Admin Dashboard:**
| File | Purpose |
|------|---------|
| `Controller/Adminhtml/Dashboard/Index.php` | Dashboard page controller |
| `Block/Adminhtml/Dashboard.php` | Dashboard data block |
| `view/adminhtml/layout/ragsync_dashboard_index.xml` | Dashboard layout |
| `view/adminhtml/templates/dashboard.phtml` | Dashboard template |
| `view/adminhtml/web/css/dashboard.css` | Dashboard styling |
| `view/adminhtml/web/js/dashboard.js` | AJAX interactions |

**AJAX Controllers:**
| Controller | Purpose |
|------------|---------|
| `Controller/Adminhtml/Sync/TestConnection.php` | Test backend connection |
| `Controller/Adminhtml/Sync/Entity.php` | Trigger entity sync |
| `Controller/Adminhtml/Sync/ProcessQueue.php` | Process pending queue |
| `Controller/Adminhtml/Sync/RetryFailed.php` | Retry failed items |
| `Controller/Adminhtml/Sync/ClearSent.php` | Clear sent items |

**Queue Grid:**
| File | Purpose |
|------|---------|
| `Controller/Adminhtml/Queue/Index.php` | Queue listing page |
| `Controller/Adminhtml/Queue/MassRetry.php` | Mass retry action |
| `Controller/Adminhtml/Queue/MassDelete.php` | Mass delete action |
| `view/adminhtml/ui_component/ragsync_queue_listing.xml` | Queue grid UI component |
| `Ui/Component/Listing/Column/Status.php` | Status column renderer |
| `Ui/Component/Listing/Column/StatusOptions.php` | Status filter options |
| `Ui/Component/Listing/Column/EntityTypeOptions.php` | Entity type options |
| `Ui/Component/Listing/Column/ActionOptions.php` | Action filter options |

### Phase 5D Completion Summary

**CLI Commands Created:**
| Command | Purpose |
|---------|---------|
| `ragsync:sync:products` | Queue products for sync (with `--store`, `--ids` options) |
| `ragsync:sync:cms` | Queue CMS pages and blocks (`--type`, `--store` options) |
| `ragsync:sync:categories` | Queue categories (`--store`, `--ids` options) |
| `ragsync:sync:promotions` | Queue promotions (`--type` option) |
| `ragsync:queue:process` | Process pending queue items |
| `ragsync:queue:status` | Display queue statistics |
| `ragsync:queue:clear` | Clear items by status (`--status`, `--force` options) |
| `ragsync:test:connection` | Test RAG backend connection |

### Phase 5E Completion Summary

**Unit Tests Created:**
| Test File | Coverage |
|-----------|----------|
| `Test/Unit/Model/ConfigTest.php` | Config model methods |
| `Test/Unit/Model/QueueTest.php` | Queue model methods and state transitions |
| `Test/Unit/Model/WebhookSenderTest.php` | HTTP client, signature, circuit breaker |
| `Test/Unit/Model/DataBuilder/ProductBuilderTest.php` | Product data extraction |
| `Test/Unit/Model/DataBuilder/CmsPageBuilderTest.php` | CMS page data extraction, document type detection |

**Integration Tests Created:**
| Test File | Coverage |
|-----------|----------|
| `Test/Integration/QueueServiceTest.php` | Queue operations, deduplication, cleanup |
| `Test/Integration/ObserverTest.php` | Event observer triggering, entity sync |

**Documentation Created:**
| Document | Purpose |
|----------|---------|
| `README.md` | Installation guide, feature overview, CLI reference |
| `docs/ADMIN_USER_GUIDE.md` | End-user guide for Magento admins |
| `docs/QA_CHECKLIST.md` | Manual testing checklist for QA |
| `Test/phpunit.xml` | PHPUnit configuration for running tests |

---

## 1. Data Entities

### 1.1 What Gets Embedded (Searchable via RAG)

| Entity | Why Embed | Document Type | Update Frequency |
|--------|-----------|---------------|------------------|
| **Products** | Search by features, specs, benefits | `product` | On save + daily full |
| **Categories** | "Show me electronics", discovery | `category` | On save + daily full |
| **CMS Pages** | Policies, FAQ, About, Contact | `policy`, `faq`, `support`, `guide` | On save + daily full |
| **CMS Blocks** | Footer info, store hours, notices | `general` | On save + daily full |
| **Promotions** | "Any discounts?", "Current sales?" | `promotion` | On save + hourly full |
| **Product Attributes** | Brand info, size guides, materials | `guide` | Weekly full sync |

### 1.2 What Gets Queried Live (Never Embed)

| Entity | Why Live Query | Cache TTL |
|--------|----------------|-----------|
| **Inventory/Stock** | Changes constantly, must be accurate | 5 minutes |
| **Prices** | Promotions, tier pricing, customer groups | 5 minutes |
| **Order Status** | Real-time tracking required | No cache |
| **Cart Contents** | Session-specific | No cache |
| **Customer Data** | Privacy, personalization | No cache |

### 1.3 CMS Page → Document Type Mapping

| CMS Identifier Pattern | Document Type | Subtype |
|------------------------|---------------|---------|
| `privacy*`, `data-protection*` | policy | privacy_policy |
| `terms*`, `conditions*` | policy | terms |
| `return*`, `refund*` | policy | return_policy |
| `warranty*`, `guarantee*` | policy | warranty |
| `shipping*`, `delivery*` | shipping | shipping_policy |
| `faq*`, `frequently-asked*`, `help*` | faq | - |
| `about*`, `our-story*`, `company*` | general | about |
| `contact*`, `reach-us*`, `support*` | support | contact |
| `size-guide*`, `fit-guide*` | guide | size_guide |
| `how-to*`, `guide*`, `tutorial*` | guide | - |
| *(default)* | general | - |

### 1.4 Data Extraction Per Entity

#### Products
```
Extract:
├── name (required)
├── sku (required)
├── description (primary content for embedding)
├── short_description
├── meta_title
├── meta_description
├── url_key
├── category_names (denormalized for context)
├── brand (if attribute exists)
├── key_attributes (filterable attributes with values)
└── image_alt_texts (accessibility descriptions)

Do NOT extract:
├── price (query live)
├── qty (query live)
├── stock_status (query live)
└── special_price (query live)
```

#### CMS Pages
```
Extract:
├── identifier (used for document type mapping)
├── title
├── content (HTML stripped, primary content)
├── meta_title
├── meta_description
└── content_heading
```

#### Categories
```
Extract:
├── name
├── description
├── url_path
├── full_path (breadcrumb: Electronics > Phones > Smartphones)
├── meta_title
├── meta_description
├── product_count (for context: "45 products in this category")
└── parent_names (for hierarchy context)
```

#### Promotions (Cart Price Rules)
```
Extract:
├── name
├── description
├── coupon_code (if public/visible)
├── discount_amount
├── discount_type (percentage, fixed)
├── conditions_summary (human-readable)
├── from_date
├── to_date
└── is_active

Do NOT extract:
├── Internal rule logic
├── Customer segment rules
└── Complex condition trees
```

---

## 2. Sync Architecture

### 2.1 High-Level Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                           MAGENTO 2                                  │
│                                                                      │
│  Admin saves entity                                                  │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Event Observer (non-blocking)                               │    │
│  │  • Validates entity should be synced                        │    │
│  │  • Writes to queue table                                     │    │
│  │  • Returns immediately (no HTTP call)                        │    │
│  └─────────────────────────────────────────────────────────────┘    │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  rag_sync_queue table                                        │    │
│  │  • entity_type, entity_id, action, priority                 │    │
│  │  • status: pending → processing → sent/failed               │    │
│  │  • Deduplicated (same entity updated twice = 1 queue item)  │    │
│  └─────────────────────────────────────────────────────────────┘    │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Cron: Process Queue (every 1 minute)                        │    │
│  │  • Fetches pending items (batch of 50)                      │    │
│  │  • Loads full entity data from Magento                       │    │
│  │  • Sends batch POST to Laravel                               │    │
│  │  • Updates queue status based on response                    │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                      │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               │ HTTPS POST (batched, signed)
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        LARAVEL RAG BACKEND                           │
│                                                                      │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Webhook Controller                                          │    │
│  │  • Validates signature                                       │    │
│  │  • Validates tenant                                          │    │
│  │  • Dispatches jobs to queue                                  │    │
│  │  • Returns 202 Accepted immediately                          │    │
│  └─────────────────────────────────────────────────────────────┘    │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Laravel Queue (magento-sync)                                │    │
│  │  • SyncProductJob → ChunkProductJob                         │    │
│  │  • SyncCmsPageJob → ChunkCmsPageJob                         │    │
│  │  • SyncCategoryJob → ChunkCategoryJob                       │    │
│  │  • SyncPromotionJob → ChunkPromotionJob                     │    │
│  └─────────────────────────────────────────────────────────────┘    │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  Python rag-ml Service                                       │    │
│  │  • /analyze → Document type classification                  │    │
│  │  • /chunk → Token-precise text chunking                     │    │
│  │  • /embed → Vector embedding generation                     │    │
│  └─────────────────────────────────────────────────────────────┘    │
│         │                                                            │
│         ▼                                                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │  PostgreSQL + pgvector                                       │    │
│  │  • chunks table (searchable embeddings)                     │    │
│  │  • sources table (metadata)                                  │    │
│  │  • magento_* tables (synced entity data)                    │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Sync Modes

| Mode | Trigger | Use Case |
|------|---------|----------|
| **Event-Driven** | Entity save/delete in admin | Real-time updates (1-2 min delay via queue) |
| **Scheduled Full** | Cron job | Catch missed updates, ensure consistency |
| **Manual** | CLI command or admin button | Initial setup, debugging, recovery |
| **Bulk Import** | After catalog import | Large data loads |

### 2.3 Cron Schedule

| Job | Cron Expression | Description |
|-----|-----------------|-------------|
| Process Queue | `* * * * *` | Every 1 minute - send pending webhooks |
| Full Product Sync | `0 2 * * *` | 2:00 AM daily - catch missed products |
| Full CMS Sync | `30 2 * * *` | 2:30 AM daily - catch missed pages |
| Full Category Sync | `0 3 * * *` | 3:00 AM daily - catch missed categories |
| Promotions Sync | `0 * * * *` | Every hour - promotions change frequently |
| Attributes Sync | `0 4 * * 0` | 4:00 AM Sunday - weekly attribute sync |
| Queue Cleanup | `0 5 * * *` | 5:00 AM daily - remove old processed items |

---

## 3. Queue Design

### 3.1 Queue Table Schema

```sql
CREATE TABLE rag_sync_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type     VARCHAR(50) NOT NULL,      -- product, cms_page, category, etc.
    entity_id       VARCHAR(50) NOT NULL,      -- Magento entity ID
    store_id        INT UNSIGNED DEFAULT 0,    -- Store view ID
    action          VARCHAR(20) NOT NULL,      -- save, delete
    priority        TINYINT DEFAULT 5,         -- 1=highest, 10=lowest
    status          VARCHAR(20) DEFAULT 'pending',
    attempts        TINYINT DEFAULT 0,
    last_attempt_at DATETIME NULL,
    error_message   TEXT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,

    UNIQUE KEY unique_entity (entity_type, entity_id, store_id, action),
    INDEX idx_status_priority (status, priority, created_at),
    INDEX idx_cleanup (status, updated_at)
);
```

### 3.2 Queue States

```
pending ──────▶ processing ──────▶ sent
                    │
                    ▼
                 failed ──────▶ pending (retry)
                    │
                    ▼ (max retries exceeded)
                  dead
```

| Status | Description |
|--------|-------------|
| `pending` | Waiting to be processed |
| `processing` | Currently being sent (prevents duplicate processing) |
| `sent` | Successfully sent to Laravel |
| `failed` | Send failed, will retry |
| `dead` | Max retries exceeded, requires manual intervention |

### 3.3 Deduplication Strategy

When the same entity is saved multiple times before the queue is processed:

```
Product 123 saved at 10:00:01 → Queue item created (pending)
Product 123 saved at 10:00:05 → Queue item updated (updated_at refreshed)
Product 123 saved at 10:00:10 → Queue item updated (updated_at refreshed)

Queue processed at 10:01:00 → Only ONE sync happens with latest data
```

**Implementation:** `INSERT ... ON DUPLICATE KEY UPDATE updated_at = NOW()`

### 3.4 Priority System

| Priority | Entity Type | Reason |
|----------|-------------|--------|
| 1 | Delete operations | Prevent stale data in search |
| 2 | Products | Core business content |
| 3 | CMS Pages | Important policies/FAQ |
| 4 | Categories | Structure updates |
| 5 | Promotions | Time-sensitive offers |
| 7 | CMS Blocks | Supporting content |
| 10 | Attributes | Rarely changes |

---

## 4. Magento Module Design

### 4.1 Module Identity

```
Vendor:     Lmarcho
Module:     RagSync
Version:    1.0.0
Requires:   Magento >= 2.4.0
```

### 4.2 Admin Configuration Structure

```
Stores → Configuration → Services → RAG Sync Integration

├── General Settings
│   ├── Enable Module: Yes/No
│   ├── Environment: Production/Staging/Development
│   ├── Debug Mode: Yes/No (logs all requests)
│   └── Log Retention Days: 30
│
├── Connection
│   ├── Webhook Base URL: https://rag-backend.example.com
│   ├── Tenant ID: your-tenant-id
│   ├── API Secret Key: ********** (encrypted)
│   ├── Connection Timeout (seconds): 30
│   └── [Test Connection] button
│
├── Entity Settings
│   ├── Products
│   │   ├── Enable Sync: Yes/No
│   │   ├── Include Disabled Products: No
│   │   ├── Include Not Visible Individually: No
│   │   ├── Sync Product Attributes: brand,color,size,material
│   │   └── Exclude Categories (IDs): 2,999 (root, test)
│   │
│   ├── CMS Pages
│   │   ├── Enable Sync: Yes/No
│   │   ├── Sync Mode: Whitelist/Blacklist
│   │   ├── Page Identifiers: privacy-policy,returns,shipping,faq,about,contact
│   │   └── Exclude Pages: no-route,home,enable-cookies
│   │
│   ├── CMS Blocks
│   │   ├── Enable Sync: Yes/No
│   │   └── Block Identifiers: footer-info,store-hours,warranty-info
│   │
│   ├── Categories
│   │   ├── Enable Sync: Yes/No
│   │   ├── Minimum Level: 2 (skip root)
│   │   ├── Include Inactive Categories: No
│   │   └── Sync Category Descriptions Only: No (include products count)
│   │
│   └── Promotions
│       ├── Enable Sync: Yes/No
│       ├── Rule Types: Cart Rules / Catalog Rules / Both
│       ├── Include Inactive Rules: No
│       └── Include Expired Rules: No
│
├── Queue Settings
│   ├── Batch Size: 50
│   ├── Max Retry Attempts: 3
│   ├── Retry Delays (minutes): 5,15,60
│   ├── Process Interval (cron): Every 1 minute
│   └── Queue Cleanup After (days): 7
│
└── Full Sync Schedule
    ├── Products: 02:00 AM daily
    ├── CMS Content: 02:30 AM daily
    ├── Categories: 03:00 AM daily
    └── Promotions: Every hour
```

### 4.3 Magento Events to Observe

| Event | Entity | Action |
|-------|--------|--------|
| `catalog_product_save_after` | Product | Queue sync |
| `catalog_product_delete_before` | Product | Queue delete |
| `clean_cache_by_tags` (product tags) | Product | Queue sync (mass update) |
| `cms_page_save_after` | CMS Page | Queue sync |
| `cms_page_delete_before` | CMS Page | Queue delete |
| `cms_block_save_after` | CMS Block | Queue sync |
| `cms_block_delete_before` | CMS Block | Queue delete |
| `catalog_category_save_after` | Category | Queue sync |
| `catalog_category_delete_before` | Category | Queue delete |
| `salesrule_rule_save_after` | Cart Rule | Queue sync |
| `salesrule_rule_delete_before` | Cart Rule | Queue delete |
| `catalogrule_rule_save_after` | Catalog Rule | Queue sync |

### 4.4 CLI Commands

```bash
# Sync commands
bin/magento rag:sync:products [--id=123] [--force]
bin/magento rag:sync:cms [--identifier=privacy-policy]
bin/magento rag:sync:categories [--id=45]
bin/magento rag:sync:promotions
bin/magento rag:sync:all [--force]

# Queue commands
bin/magento rag:queue:process [--limit=100]
bin/magento rag:queue:status
bin/magento rag:queue:retry-failed
bin/magento rag:queue:clear [--status=sent] [--older-than=7days]

# Utility commands
bin/magento rag:test-connection
bin/magento rag:export:products --output=/tmp/products.json
```

### 4.5 Admin Dashboard

```
Marketing → RAG Sync → Dashboard

┌─────────────────────────────────────────────────────────────────────┐
│                         RAG SYNC DASHBOARD                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  CONNECTION STATUS                                                  │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ ● Connected to: https://rag-backend.example.com               │ │
│  │   Tenant: kiddoz-store | Last ping: 2 seconds ago | Latency: 45ms │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  SYNC STATUS                                                        │
│  ┌─────────────┬──────────────┬─────────┬────────┬───────────────┐ │
│  │ Entity      │ Last Sync    │ Status  │ Count  │ Actions       │ │
│  ├─────────────┼──────────────┼─────────┼────────┼───────────────┤ │
│  │ Products    │ 2 min ago    │ ✓ OK    │ 1,234  │ [Sync] [View] │ │
│  │ CMS Pages   │ 5 min ago    │ ✓ OK    │ 12     │ [Sync] [View] │ │
│  │ CMS Blocks  │ 1 hour ago   │ ✓ OK    │ 8      │ [Sync] [View] │ │
│  │ Categories  │ 3 hours ago  │ ✓ OK    │ 45     │ [Sync] [View] │ │
│  │ Promotions  │ 30 min ago   │ ✓ OK    │ 5      │ [Sync] [View] │ │
│  └─────────────┴──────────────┴─────────┴────────┴───────────────┘ │
│                                                                     │
│  QUEUE STATUS                                                       │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  Pending    Processing    Sent (24h)    Failed    Dead        │ │
│  │    3           1            847           0         0          │ │
│  │                                                                │ │
│  │  [Process Now]  [Retry Failed]  [Clear Sent]  [View Queue]    │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  RECENT ACTIVITY (Last 50)                                          │
│  ┌─────────────┬──────────┬──────────────┬────────┬──────────────┐ │
│  │ Time        │ Entity   │ ID/Name      │ Action │ Status       │ │
│  ├─────────────┼──────────┼──────────────┼────────┼──────────────┤ │
│  │ 2 min ago   │ Product  │ SKU-12345    │ Update │ ✓ Synced     │ │
│  │ 5 min ago   │ CMS Page │ returns      │ Update │ ✓ Synced     │ │
│  │ 10 min ago  │ Product  │ SKU-67890    │ Create │ ✓ Synced     │ │
│  │ 15 min ago  │ Category │ Electronics  │ Update │ ✓ Synced     │ │
│  └─────────────┴──────────┴──────────────┴────────┴──────────────┘ │
│                                                                     │
│  ERRORS (if any)                                                    │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │ No errors in the last 24 hours                                │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 5. Laravel Backend Updates

### 5.1 New Models Required

| Model | Table | Purpose |
|-------|-------|---------|
| `MagentoCmsPage` | `magento_cms_pages` | Store CMS page content |
| `MagentoCmsBlock` | `magento_cms_blocks` | Store CMS block content |
| `MagentoPromotion` | `magento_promotions` | Store promotion rules |
| `MagentoAttribute` | `magento_attributes` | Store attribute options |

### 5.2 New Jobs Required

| Job | Purpose | Queue |
|-----|---------|-------|
| `SyncMagentoCmsPageJob` | Upsert CMS page to local DB | magento-sync |
| `ChunkCmsPageJob` | Chunk and embed CMS content | magento-sync |
| `SyncMagentoCmsBlockJob` | Upsert CMS block to local DB | magento-sync |
| `ChunkCmsBlockJob` | Chunk and embed block content | magento-sync |
| `SyncMagentoPromotionJob` | Upsert promotion to local DB | magento-sync |
| `ChunkPromotionJob` | Chunk and embed promotion | magento-sync |

### 5.3 New API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/webhooks/magento/{tenant}/batch` | Receive batched entity updates |
| POST | `/api/webhooks/magento/{tenant}/cms-page` | Single CMS page webhook |
| POST | `/api/webhooks/magento/{tenant}/cms-block` | Single CMS block webhook |
| POST | `/api/webhooks/magento/{tenant}/promotion` | Single promotion webhook |
| GET | `/api/webhooks/magento/{tenant}/status` | Health check for Magento |

### 5.4 Webhook Payload Structure

```json
{
  "batch_id": "uuid-v4",
  "timestamp": "2026-01-02T10:30:00Z",
  "items": [
    {
      "type": "product",
      "id": "123",
      "action": "save",
      "data": {
        "sku": "IPHONE-15-PRO",
        "name": "iPhone 15 Pro",
        "description": "...",
        "categories": ["Electronics", "Phones"],
        "attributes": {"brand": "Apple", "color": "Black"}
      }
    },
    {
      "type": "cms_page",
      "id": "privacy-policy",
      "action": "save",
      "data": {
        "identifier": "privacy-policy",
        "title": "Privacy Policy",
        "content": "...",
        "document_type_hint": "policy"
      }
    }
  ],
  "signature": "hmac-sha256-signature"
}
```

### 5.5 New Artisan Commands

```bash
php artisan magento:sync:cms [--identifier=privacy-policy]
php artisan magento:sync:promotions
php artisan magento:sync:blocks
php artisan magento:status  # Show sync status for all entities
```

---

## 6. Security

### 6.1 Authentication

| Layer | Method | Implementation |
|-------|--------|----------------|
| Transport | HTTPS/TLS 1.3 | Mandatory for all webhook calls |
| Request Signing | HMAC-SHA256 | Sign payload with shared secret |
| Tenant Isolation | Tenant ID in URL | Validate tenant exists and is active |
| IP Allowlist | Optional | Restrict to Magento server IPs |

### 6.2 Signature Verification

```
Magento side:
1. payload = JSON string of request body
2. signature = HMAC-SHA256(payload, secret_key)
3. Add header: X-Rag-Signature: sha256={signature}

Laravel side:
1. Extract signature from header
2. Compute expected = HMAC-SHA256(request_body, secret_key)
3. Compare using timing-safe comparison
4. Reject if mismatch (401 Unauthorized)
```

### 6.3 Secrets Management

| Secret | Storage Location | Rotation |
|--------|------------------|----------|
| API Secret Key | Magento: `core_config_data` (encrypted) | Quarterly |
| | Laravel: `.env` or secrets manager | Quarterly |
| Tenant ID | Magento: `core_config_data` | Never (identifier) |

### 6.4 Data Privacy

- **No customer PII** in sync (no emails, addresses, order details)
- **No payment data** ever transmitted
- **Product descriptions** are business content, not private
- **CMS content** is public-facing, already on website
- **Log redaction** - don't log full payloads in production

---

## 7. Error Handling & Retry

### 7.1 Error Categories

| Category | HTTP Code | Retry? | Example |
|----------|-----------|--------|---------|
| Validation Error | 400 | No | Invalid payload format |
| Auth Error | 401, 403 | No | Bad signature, unknown tenant |
| Not Found | 404 | No | Entity deleted during processing |
| Rate Limited | 429 | Yes | Too many requests |
| Server Error | 500, 502, 503 | Yes | Backend temporarily down |
| Timeout | - | Yes | Network issues |

### 7.2 Retry Strategy

```
Attempt 1: Immediate (in cron run)
    │
    └── Failed?
            │
            ▼
        Wait 5 minutes
            │
            ▼
Attempt 2: Retry
    │
    └── Failed?
            │
            ▼
        Wait 15 minutes
            │
            ▼
Attempt 3: Retry
    │
    └── Failed?
            │
            ▼
        Wait 60 minutes
            │
            ▼
Attempt 4: Final retry
    │
    └── Failed?
            │
            ▼
        Mark as DEAD
        Send admin notification
```

### 7.3 Dead Letter Handling

When an item reaches `dead` status:

1. **Email notification** to configured admin email
2. **Log entry** with full error details
3. **Dashboard indicator** showing dead items count
4. **Manual retry** available via admin UI or CLI
5. **Auto-expire** after 30 days if not resolved

### 7.4 Circuit Breaker

If too many consecutive failures:

```
Normal operation
    │
    ▼ (5 consecutive failures)

CIRCUIT OPEN - Stop sending for 5 minutes
    │
    ▼ (after 5 minutes)

HALF-OPEN - Try one request
    │
    ├── Success → CLOSED (normal operation)
    │
    └── Failure → OPEN (wait another 5 minutes)
```

---

## 8. Monitoring & Alerting

### 8.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Queue depth | Gauge | > 500 items pending |
| Queue age | Gauge | Oldest item > 30 minutes |
| Sync success rate | Percentage | < 95% over 1 hour |
| Webhook latency | Histogram | p95 > 5 seconds |
| Failed items count | Counter | > 10 in 1 hour |
| Dead items count | Counter | > 0 (immediate) |

### 8.2 Logging

| Level | What to Log |
|-------|-------------|
| DEBUG | Full request/response payloads (dev only) |
| INFO | Sync completed: entity type, ID, duration |
| WARNING | Retry triggered, slow responses |
| ERROR | Sync failed, validation errors |
| CRITICAL | Circuit breaker opened, dead items |

### 8.3 Log Format (Structured)

```json
{
  "timestamp": "2026-01-02T10:30:00Z",
  "level": "INFO",
  "message": "Sync completed",
  "context": {
    "entity_type": "product",
    "entity_id": "123",
    "action": "save",
    "duration_ms": 245,
    "queue_id": 456
  }
}
```

### 8.4 Alerting Channels

| Severity | Channel | Response Time |
|----------|---------|---------------|
| Critical | PagerDuty/SMS | < 15 minutes |
| Error | Slack + Email | < 1 hour |
| Warning | Slack | Next business day |
| Info | Dashboard only | - |

---

## 9. Performance Considerations

### 9.1 Magento Side

| Concern | Solution |
|---------|----------|
| Observer slowing down saves | Write to queue only, no HTTP in observer |
| Large catalog (100k+ products) | Batch processing, pagination in full sync |
| Memory usage in cron | Process in chunks of 50, clear memory between |
| Database load | Use indexes on queue table, archive old data |

### 9.2 Laravel Side

| Concern | Solution |
|---------|----------|
| Webhook processing time | Return 202 immediately, process async |
| Large batches | Split into individual jobs |
| Embedding API rate limits | Queue with rate limiting |
| Database connections | Use queue workers with limited connections |

### 9.3 Batch Sizes

| Operation | Recommended Batch Size |
|-----------|------------------------|
| Queue processing (Magento) | 50 items per cron run |
| Webhook payload | 20-50 items per request |
| Embedding generation | 10 texts per API call |
| Full sync pagination | 100 entities per page |

### 9.4 Estimated Processing Times

| Entity Type | Avg Processing Time | Notes |
|-------------|--------------------:|-------|
| Product (simple) | 500ms | Chunk + embed |
| Product (with attributes) | 800ms | More content |
| CMS Page (short) | 300ms | Usually 1 chunk |
| CMS Page (long FAQ) | 1-2s | Multiple chunks |
| Category | 200ms | Minimal content |
| Promotion | 150ms | Short descriptions |

---

## 10. Implementation Phases

### Phase 5A: Laravel Backend Preparation ✅ COMPLETE

**Objective:** Prepare Laravel to receive new entity types

**Status:** Completed on 2026-01-02

| Task | Status | Deliverable |
|------|--------|-------------|
| Create MagentoCmsPage model + migration | ✅ Done | `app/Models/MagentoCmsPage.php` |
| Create MagentoCmsBlock model + migration | ✅ Done | `app/Models/MagentoCmsBlock.php` |
| Create MagentoPromotion model + migration | ✅ Done | `app/Models/MagentoPromotion.php` |
| Create SyncMagentoCmsPageJob | ✅ Done | `app/Jobs/SyncMagentoCmsPagesJob.php` |
| Create ChunkCmsPageJob | ✅ Done | `app/Jobs/ChunkCmsPageJob.php` |
| Create batch webhook endpoint | ✅ Done | `POST /api/webhooks/magento/{tenant}/batch` |
| Update MagentoWebhookController | ✅ Done | All new entity type handlers |
| Add artisan commands | ✅ Done | `magento:sync:cms`, `magento:sync:promotions`, `magento:status` |
| Write unit tests | ⏳ Pending | To be completed in Phase 5E |

### Phase 5B: Magento Module Core (3 days)

**Objective:** Build the core sync functionality

| Task | Effort | Deliverable |
|------|--------|-------------|
| Module skeleton + registration | 1h | Basic module structure |
| Configuration (system.xml) | 3h | All admin settings |
| Queue table schema | 1h | InstallSchema.php |
| Queue model + resource model | 2h | Queue management classes |
| Config model (read settings) | 2h | Config.php |
| WebhookSender (HTTP client) | 3h | Guzzle-based sender |
| Data builders (Product, CMS, Category) | 4h | Extract entity data |
| Event observers (all entities) | 4h | Queue on save/delete |
| Cron: ProcessQueue | 3h | Send pending items |
| Cron: Full sync jobs | 4h | Scheduled full syncs |

### Phase 5C: Magento Admin UI (1 day)

**Objective:** Admin dashboard and manual controls

| Task | Effort | Deliverable |
|------|--------|-------------|
| Admin menu registration | 1h | menu.xml |
| Dashboard controller + block | 2h | Dashboard display |
| Dashboard template | 2h | UI layout |
| Sync buttons (AJAX) | 2h | Manual sync triggers |
| Queue viewer grid | 2h | View queue items |

### Phase 5D: CLI Commands (0.5 days)

**Objective:** Command-line tools for operations

| Task | Effort | Deliverable |
|------|--------|-------------|
| rag:sync:* commands | 2h | Sync commands |
| rag:queue:* commands | 2h | Queue management |
| rag:test-connection | 1h | Connection test |

### Phase 5E: Testing & Documentation (1.5 days)

**Objective:** Ensure quality and maintainability

| Task | Effort | Deliverable |
|------|--------|-------------|
| Unit tests (Magento module) | 4h | PHPUnit tests |
| Integration tests | 3h | End-to-end flow |
| Manual QA checklist | 2h | Test scenarios |
| README documentation | 2h | Installation guide |
| Admin user guide | 1h | How to use |

### Phase Summary

| Phase | Duration | Dependencies | Status |
|-------|----------|--------------|--------|
| 5A: Laravel Prep | 2 days | None | ✅ Complete |
| 5B: Magento Core | 3 days | 5A (for testing) | ✅ Complete |
| 5C: Magento Admin | 1 day | 5B | ✅ Complete |
| 5D: CLI Commands | 0.5 days | 5B | ✅ Complete |
| 5E: Testing & Docs | 1.5 days | All above | ✅ Complete |

**Total: ~8 days** | **Progress: ALL PHASES COMPLETE (100%)**

---

## 11. Testing Strategy

### 11.1 Unit Tests

| Component | Test Cases |
|-----------|------------|
| Queue Model | Create, update, deduplication, state transitions |
| Data Builders | Extract correct fields, handle missing data |
| Config Model | Read settings, defaults, encrypted values |
| WebhookSender | Request format, signature, error handling |

### 11.2 Integration Tests

| Scenario | Expected Result |
|----------|-----------------|
| Product save → Queue → Webhook → Laravel | Chunk created in DB |
| CMS page save → Sync | Correct document type assigned |
| Bulk product import | Queue populated, processed in batches |
| Webhook failure | Retry scheduled, eventually succeeds |
| Max retries exceeded | Item marked dead, admin notified |

### 11.3 Manual Test Checklist

```
□ Install module on clean Magento instance
□ Configure connection settings
□ Test connection button works
□ Save a product → appears in queue
□ Queue processes within 1 minute
□ Product appears in RAG search results
□ Edit CMS page → syncs correctly
□ Delete product → removed from RAG
□ Disable sync → saves don't queue
□ Full sync command works
□ Dashboard shows correct statistics
□ Failed item appears in dashboard
□ Retry failed item works
□ CLI commands execute correctly
□ Logs contain expected entries
```

### 11.4 Performance Tests

| Test | Target |
|------|--------|
| 1000 products full sync | < 30 minutes |
| Queue processing 50 items | < 60 seconds |
| Single product save → synced | < 2 minutes |
| Dashboard load time | < 2 seconds |

---

## 12. Deployment Checklist

### 12.1 Pre-Deployment

```
□ Code reviewed and approved
□ All tests passing
□ Documentation complete
□ Staging environment tested
□ Rollback plan documented
□ Maintenance window scheduled
□ Team notified
```

### 12.2 Magento Deployment

```
□ Backup database
□ Enable maintenance mode
□ Deploy module files
□ Run: bin/magento setup:upgrade
□ Run: bin/magento setup:di:compile
□ Run: bin/magento cache:clean
□ Configure settings in admin
□ Test connection
□ Disable maintenance mode
□ Trigger initial full sync
□ Monitor logs for errors
```

### 12.3 Laravel Deployment

```
□ Deploy code changes
□ Run: php artisan migrate
□ Clear caches: php artisan cache:clear
□ Restart queue workers
□ Verify webhook endpoint accessible
□ Check queue processing
```

### 12.4 Post-Deployment

```
□ Verify products appearing in RAG
□ Test chatbot with product questions
□ Test chatbot with policy questions
□ Check dashboard statistics
□ Monitor error rates for 24 hours
□ Remove from maintenance window
```

---

## 13. Rollback Plan

### 13.1 Magento Module Rollback

```bash
# 1. Enable maintenance mode
bin/magento maintenance:enable

# 2. Disable module
bin/magento module:disable Custom_RagSync

# 3. Remove module files
rm -rf app/code/Custom/RagSync

# 4. Clear generated code
rm -rf generated/code/*

# 5. Upgrade to remove from setup_module
bin/magento setup:upgrade

# 6. Disable maintenance mode
bin/magento maintenance:disable
```

### 13.2 Laravel Rollback

```bash
# 1. Rollback migrations
php artisan migrate:rollback --step=X

# 2. Deploy previous code version
git checkout previous-release

# 3. Clear caches
php artisan cache:clear

# 4. Restart workers
php artisan queue:restart
```

### 13.3 Data Cleanup (if needed)

```sql
-- Remove synced chunks (Laravel DB)
DELETE FROM chunks WHERE source_type IN ('magento_cms_page', 'magento_cms_block', 'magento_promotion');

-- Remove synced entities (Laravel DB)
TRUNCATE TABLE magento_cms_pages;
TRUNCATE TABLE magento_cms_blocks;
TRUNCATE TABLE magento_promotions;

-- Remove queue table (Magento DB)
DROP TABLE IF EXISTS rag_sync_queue;
```

---

## Appendix A: File Structure

### Magento Module

```
app/code/Custom/RagSync/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   ├── di.xml
│   ├── config.xml (defaults)
│   ├── events.xml
│   ├── crontab.xml
│   └── adminhtml/
│       ├── system.xml
│       ├── menu.xml
│       └── routes.xml
├── Api/
│   └── Data/
│       └── QueueItemInterface.php
├── Model/
│   ├── Config.php
│   ├── Queue.php
│   ├── WebhookSender.php
│   ├── CircuitBreaker.php
│   ├── ResourceModel/
│   │   ├── Queue.php
│   │   └── Queue/
│   │       └── Collection.php
│   └── DataBuilder/
│       ├── ProductBuilder.php
│       ├── CmsPageBuilder.php
│       ├── CmsBlockBuilder.php
│       ├── CategoryBuilder.php
│       └── PromotionBuilder.php
├── Observer/
│   ├── ProductSaveObserver.php
│   ├── ProductDeleteObserver.php
│   ├── CmsPageSaveObserver.php
│   ├── CmsPageDeleteObserver.php
│   ├── CmsBlockSaveObserver.php
│   ├── CategorySaveObserver.php
│   └── PromotionSaveObserver.php
├── Cron/
│   ├── ProcessQueue.php
│   ├── FullProductSync.php
│   ├── FullCmsSync.php
│   ├── FullCategorySync.php
│   ├── PromotionSync.php
│   └── CleanupQueue.php
├── Controller/
│   └── Adminhtml/
│       ├── Dashboard/
│       │   ├── Index.php
│       │   └── Sync.php
│       └── Queue/
│           ├── Grid.php
│           └── Retry.php
├── Block/
│   └── Adminhtml/
│       ├── Dashboard.php
│       └── Queue/
│           └── Grid.php
├── Console/
│   └── Command/
│       ├── SyncProductsCommand.php
│       ├── SyncCmsCommand.php
│       ├── SyncCategoriesCommand.php
│       ├── SyncPromotionsCommand.php
│       ├── SyncAllCommand.php
│       ├── ProcessQueueCommand.php
│       ├── QueueStatusCommand.php
│       └── TestConnectionCommand.php
├── Setup/
│   └── InstallSchema.php
├── view/
│   └── adminhtml/
│       ├── layout/
│       │   ├── ragsync_dashboard_index.xml
│       │   └── ragsync_queue_grid.xml
│       ├── templates/
│       │   └── dashboard.phtml
│       └── web/
│           ├── css/
│           │   └── dashboard.css
│           └── js/
│               └── dashboard.js
├── i18n/
│   └── en_US.csv
├── Test/
│   └── Unit/
│       ├── Model/
│       │   ├── ConfigTest.php
│       │   ├── QueueTest.php
│       │   └── WebhookSenderTest.php
│       └── DataBuilder/
│           ├── ProductBuilderTest.php
│           └── CmsPageBuilderTest.php
└── README.md
```

### Laravel Updates

```
rag-backend/
├── app/
│   ├── Models/
│   │   ├── MagentoCmsPage.php (NEW)
│   │   ├── MagentoCmsBlock.php (NEW)
│   │   └── MagentoPromotion.php (NEW)
│   ├── Jobs/
│   │   ├── SyncMagentoCmsPageJob.php (NEW)
│   │   ├── ChunkCmsPageJob.php (NEW)
│   │   ├── SyncMagentoCmsBlockJob.php (NEW)
│   │   ├── ChunkCmsBlockJob.php (NEW)
│   │   ├── SyncMagentoPromotionJob.php (NEW)
│   │   └── ChunkPromotionJob.php (NEW)
│   ├── Http/Controllers/
│   │   └── MagentoWebhookController.php (UPDATE)
│   └── Console/Commands/
│       ├── MagentoSyncCms.php (NEW)
│       └── MagentoSyncPromotions.php (NEW)
├── database/migrations/
│   ├── XXXX_create_magento_cms_pages_table.php (NEW)
│   ├── XXXX_create_magento_cms_blocks_table.php (NEW)
│   └── XXXX_create_magento_promotions_table.php (NEW)
└── tests/
    └── Feature/
        └── MagentoWebhookTest.php (UPDATE)
```

---

## Appendix B: Configuration Reference

### Environment Variables (Laravel)

```env
# Magento Sync
MAGENTO_SYNC_ENABLED=true
MAGENTO_SYNC_SECRET=your-shared-secret-key
MAGENTO_SYNC_QUEUE=magento-sync
MAGENTO_SYNC_TIMEOUT=30
```

### Sample Magento Config

```xml
<!-- etc/config.xml defaults -->
<default>
    <rag_sync>
        <general>
            <enabled>0</enabled>
            <debug>0</debug>
        </general>
        <connection>
            <timeout>30</timeout>
        </connection>
        <queue>
            <batch_size>50</batch_size>
            <max_retries>3</max_retries>
            <retry_delays>5,15,60</retry_delays>
            <cleanup_days>7</cleanup_days>
        </queue>
        <products>
            <enabled>1</enabled>
            <include_disabled>0</include_disabled>
            <include_not_visible>0</include_not_visible>
        </products>
        <cms_pages>
            <enabled>1</enabled>
            <sync_mode>whitelist</sync_mode>
            <identifiers>privacy-policy,returns,shipping,faq,about,contact</identifiers>
        </cms_pages>
        <categories>
            <enabled>1</enabled>
            <min_level>2</min_level>
            <include_inactive>0</include_inactive>
        </categories>
        <promotions>
            <enabled>1</enabled>
            <rule_types>both</rule_types>
        </promotions>
    </rag_sync>
</default>
```

---

*Document Version: 1.3*
*Created: January 2026*
*Last Updated: 2026-01-02*
*Author: RAG Integration Team*

**Changelog:**
- v1.3 (2026-01-02): **ALL PHASES COMPLETE** - Phase 5E added (unit tests, integration tests, documentation)
- v1.2 (2026-01-02): Phases 5B, 5C, 5D completed - Full Magento module implementation
- v1.1 (2026-01-02): Phase 5A completed - Laravel backend preparation done
- v1.0 (January 2026): Initial plan document
