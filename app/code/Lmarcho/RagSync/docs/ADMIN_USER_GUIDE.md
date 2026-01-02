# RAG Sync Admin User Guide

This guide explains how to use the RAG Sync module from the Magento Admin Panel.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Configuration](#configuration)
3. [Dashboard Overview](#dashboard-overview)
4. [Managing the Queue](#managing-the-queue)
5. [Syncing Content](#syncing-content)
6. [Troubleshooting](#troubleshooting)
7. [Best Practices](#best-practices)

---

## Getting Started

### Accessing RAG Sync

1. Log in to your Magento Admin Panel
2. Navigate to **Marketing → RAG Sync → Dashboard**

### Initial Setup Checklist

Before using the module, complete these steps:

- [ ] Obtain your RAG backend credentials (Webhook URL, Tenant ID, API Secret)
- [ ] Configure the module settings
- [ ] Test the connection
- [ ] Trigger an initial full sync

---

## Configuration

Navigate to **Stores → Configuration → Services → RAG Sync Integration**

### General Settings

| Setting | Description | Recommendation |
|---------|-------------|----------------|
| **Enable Module** | Turns sync on/off | Enable after configuration |
| **Environment** | Production/Staging/Development | Match your environment |
| **Debug Mode** | Enables detailed logging | Enable only when troubleshooting |

### Connection Settings

| Setting | Where to Get It |
|---------|-----------------|
| **Webhook Base URL** | Provided by your RAG backend admin |
| **Tenant ID** | Your unique identifier in the RAG system |
| **API Secret Key** | Shared secret (keep confidential!) |
| **Connection Timeout** | Default 30 seconds is usually fine |

### Entity Settings

#### Products

| Setting | Description |
|---------|-------------|
| **Enable Sync** | Toggle product synchronization |
| **Include Disabled Products** | Usually set to No |
| **Include Not Visible Individually** | Usually set to No |
| **Sync Product Attributes** | Comma-separated list: `brand,color,size,material` |

#### CMS Pages

| Setting | Description |
|---------|-------------|
| **Enable Sync** | Toggle CMS page synchronization |
| **Sync Mode** | Whitelist (only listed) or Blacklist (all except listed) |
| **Page Identifiers** | `privacy-policy,returns,shipping,faq,about,contact` |

**Tip**: Use whitelist mode to sync only customer-facing policy pages.

#### Categories

| Setting | Description |
|---------|-------------|
| **Enable Sync** | Toggle category synchronization |
| **Minimum Level** | Skip root categories (recommended: 2) |
| **Include Inactive** | Usually set to No |

#### Promotions

| Setting | Description |
|---------|-------------|
| **Enable Sync** | Toggle promotion synchronization |
| **Rule Types** | Cart Rules, Catalog Rules, or Both |

### Queue Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Batch Size** | Items sent per request | 50 |
| **Max Retries** | Attempts before giving up | 3 |
| **Retry Delays** | Minutes between retries | 5,15,60 |

---

## Dashboard Overview

The Dashboard (**Marketing → RAG Sync → Dashboard**) shows:

### Connection Status

```
┌─────────────────────────────────────────────────────────────┐
│ ● Connected to: https://rag-backend.example.com             │
│   Tenant: your-tenant | Last ping: 2s ago | Latency: 45ms   │
└─────────────────────────────────────────────────────────────┘
```

**Status Indicators:**
- 🟢 **Connected** - All systems operational
- 🟡 **Warning** - High latency or intermittent issues
- 🔴 **Disconnected** - Cannot reach backend

### Queue Status

```
┌───────────────────────────────────────────────────────────────┐
│  Pending    Processing    Sent (24h)    Failed    Dead        │
│    12           2            1,847         0        0          │
└───────────────────────────────────────────────────────────────┘
```

**What Each Status Means:**

| Status | Meaning | Action Needed? |
|--------|---------|----------------|
| **Pending** | Waiting to be sent | No - will process automatically |
| **Processing** | Currently being sent | No - in progress |
| **Sent** | Successfully delivered | No - all good! |
| **Failed** | Send failed, will retry | Monitor - may resolve automatically |
| **Dead** | Max retries exceeded | Yes - investigate and retry manually |

### Entity Sync Status

Shows last sync time and item count for each entity type:

| Entity | Last Sync | Status | Count |
|--------|-----------|--------|-------|
| Products | 2 min ago | ✓ OK | 1,234 |
| CMS Pages | 5 min ago | ✓ OK | 12 |
| Categories | 1 hr ago | ✓ OK | 45 |

### Quick Actions

| Button | What It Does |
|--------|--------------|
| **Test Connection** | Verifies backend is reachable |
| **Process Queue** | Immediately sends pending items |
| **Retry Failed** | Resets failed items to pending |
| **Clear Sent** | Removes successfully sent items |

---

## Managing the Queue

### Viewing the Queue

1. Go to **Marketing → RAG Sync → Queue**
2. Or click **View Queue** on the Dashboard

### Filtering Items

Use the filter dropdowns to find specific items:
- **Status**: Pending, Processing, Sent, Failed, Dead
- **Entity Type**: Product, CMS Page, Category, etc.
- **Action**: Upsert (create/update) or Delete

### Mass Actions

Select items using checkboxes, then choose an action:

| Action | What It Does | When to Use |
|--------|--------------|-------------|
| **Retry** | Resets to pending with 0 attempts | After fixing backend issues |
| **Delete** | Removes from queue | To clear obsolete items |

### Understanding Queue Entries

Each queue item shows:
- **ID**: Unique queue identifier
- **Entity Type**: What kind of content (product, cms_page, etc.)
- **Entity ID**: The Magento ID of the item
- **Store ID**: Which store view
- **Action**: Upsert or Delete
- **Status**: Current state
- **Priority**: 1 (highest) to 10 (lowest)
- **Attempts**: How many times we tried to send
- **Error**: Last error message (if failed)
- **Created/Updated**: Timestamps

---

## Syncing Content

### Automatic Sync

Content syncs automatically when you:
- Save a product
- Save a CMS page or block
- Save a category
- Save a promotion rule

The sync happens in the background - you won't notice any delay when saving.

### Manual Sync (Dashboard)

Use the entity sync buttons on the Dashboard:

1. Click the **Sync** button next to an entity type
2. Choose sync options (if prompted)
3. Items are added to the queue
4. Queue processes automatically every minute

### Full Sync Schedule

Full syncs run automatically via cron:

| Entity | Schedule | Time |
|--------|----------|------|
| Products | Daily | 2:00 AM |
| CMS Content | Daily | 2:30 AM |
| Categories | Daily | 3:00 AM |
| Promotions | Hourly | :00 |

---

## Troubleshooting

### Common Issues

#### "Connection Failed" Error

**Cause**: Cannot reach the RAG backend

**Solutions**:
1. Check the Webhook URL is correct
2. Verify your server can make outbound HTTPS requests
3. Check if the backend is experiencing downtime
4. Verify API Secret matches on both sides

#### High Number of Failed Items

**Cause**: Backend rejecting requests or network issues

**Solutions**:
1. Check the error messages in the queue grid
2. Click **Retry Failed** after fixing issues
3. Contact your RAG backend administrator

#### Items Stuck in "Processing"

**Cause**: Previous sync attempt didn't complete

**Solutions**:
1. Wait for the reset cron job (runs every 15 minutes)
2. Or manually retry via **Retry Failed** button

#### Queue Not Processing

**Cause**: Cron not running or module issue

**Solutions**:
1. Verify Magento cron is running
2. Check if module is enabled in configuration
3. Review logs at `var/log/ragsync.log`

### Checking Logs

1. Access via SSH: `tail -f var/log/ragsync.log`
2. Enable Debug Mode for more details

**Log Levels**:
- **INFO**: Normal operations
- **WARNING**: Minor issues, retrying
- **ERROR**: Failed operations
- **CRITICAL**: System-level problems

---

## Best Practices

### Initial Setup

1. **Start with a small batch**: Test with a few products first
2. **Use staging environment**: Test configuration before production
3. **Monitor the first full sync**: Watch for errors

### Ongoing Maintenance

1. **Check dashboard daily**: Look for failed or dead items
2. **Clear sent items weekly**: Keeps the queue manageable
3. **Review logs when issues arise**: Enable debug mode temporarily

### Performance Tips

1. **Batch size**: Increase if backend handles it well, decrease if timeouts occur
2. **Schedule full syncs off-peak**: Default 2-3 AM is usually good
3. **Don't sync unnecessary content**: Use whitelist mode for CMS pages

### Security

1. **Keep API Secret confidential**: Never share or commit to version control
2. **Use HTTPS**: Always use secure webhook URLs
3. **Rotate secrets quarterly**: Update API Secret periodically

---

## Glossary

| Term | Definition |
|------|------------|
| **Queue** | Temporary storage for items waiting to sync |
| **Webhook** | HTTP endpoint that receives sync data |
| **Tenant** | Your unique identifier in the RAG system |
| **Circuit Breaker** | Automatic pause when backend is down |
| **Deduplication** | Preventing duplicate sync for same item |
| **Upsert** | Create if new, update if exists |

---

## Quick Reference

### Menu Locations

| Feature | Location |
|---------|----------|
| Dashboard | Marketing → RAG Sync → Dashboard |
| Queue | Marketing → RAG Sync → Queue |
| Configuration | Stores → Configuration → Services → RAG Sync |

### Status Colors

| Color | Meaning |
|-------|---------|
| 🟢 Green | Healthy / Sent |
| 🟡 Yellow | Warning / Pending |
| 🔴 Red | Error / Failed / Dead |

### Support Contacts

- Technical Issues: [Your Support Email]
- RAG Backend: [Backend Admin Contact]
- Documentation: See README.md in module directory
