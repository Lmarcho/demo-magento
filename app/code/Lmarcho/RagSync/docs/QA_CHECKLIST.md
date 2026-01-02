# RAG Sync Module - QA Checklist

Use this checklist for manual testing before deployment.

## Pre-Deployment Testing

### Installation

- [ ] Module installs without errors via `bin/magento setup:upgrade`
- [ ] Module compiles without errors via `bin/magento setup:di:compile`
- [ ] No errors in `var/log/system.log` after installation
- [ ] Database tables created: `rag_sync_queue`, `rag_sync_circuit_breaker`, `rag_sync_log`

### Configuration

- [ ] Configuration section appears in Admin → Stores → Configuration → Services → RAG Sync
- [ ] All configuration fields are present and properly labeled
- [ ] API Secret field is masked (password type)
- [ ] Default values are populated correctly
- [ ] Saving configuration works without errors
- [ ] Configuration values persist after cache clear

### Admin Menu

- [ ] Menu appears under Marketing → RAG Sync
- [ ] Dashboard link works
- [ ] Queue link works
- [ ] ACL permissions restrict access appropriately

---

## Dashboard Testing

### Connection Status

- [ ] **Test Connection** button is functional
- [ ] Success message shows when backend is reachable
- [ ] Error message shows when backend is unreachable
- [ ] Connection status updates reflect actual state

### Queue Statistics

- [ ] Pending count is accurate
- [ ] Processing count is accurate
- [ ] Sent count is accurate
- [ ] Failed count is accurate
- [ ] Dead count is accurate
- [ ] Total count matches sum of individual counts

### Entity Status

- [ ] Product sync status displays correctly
- [ ] CMS Page sync status displays correctly
- [ ] CMS Block sync status displays correctly
- [ ] Category sync status displays correctly
- [ ] Promotion sync status displays correctly

### Action Buttons

- [ ] **Process Queue** button works and shows feedback
- [ ] **Retry Failed** button works and shows feedback
- [ ] **Clear Sent** button works and shows feedback
- [ ] **Sync** buttons for each entity type work

---

## Queue Grid Testing

### Display

- [ ] Grid loads without errors
- [ ] All columns display correctly
- [ ] Status badges show correct colors
- [ ] Pagination works correctly

### Filtering

- [ ] Filter by Status works
- [ ] Filter by Entity Type works
- [ ] Filter by Action works
- [ ] Filter by Date Range works
- [ ] Clear filters works

### Sorting

- [ ] Sort by ID works
- [ ] Sort by Entity Type works
- [ ] Sort by Status works
- [ ] Sort by Created At works
- [ ] Sort by Priority works

### Mass Actions

- [ ] Select All works
- [ ] Mass Retry action works
- [ ] Mass Delete action works
- [ ] Confirmation dialogs appear

---

## Entity Sync Testing

### Product Sync

- [ ] Saving a new product creates queue entry
- [ ] Saving an existing product creates/updates queue entry
- [ ] Deleting a product creates delete queue entry
- [ ] Disabled products are excluded (if configured)
- [ ] Not visible individually products are excluded (if configured)
- [ ] Queue entry has correct priority (2 for products, 1 for delete)
- [ ] Deduplication works (multiple saves = one queue entry)

### CMS Page Sync

- [ ] Saving a whitelisted CMS page creates queue entry
- [ ] Non-whitelisted pages are excluded (whitelist mode)
- [ ] Blacklisted pages are excluded (blacklist mode)
- [ ] Deleting a CMS page creates delete queue entry
- [ ] Document type is correctly detected:
  - [ ] `privacy-policy` → policy
  - [ ] `faq` → faq
  - [ ] `contact-us` → support
  - [ ] `size-guide` → guide

### CMS Block Sync

- [ ] Saving a CMS block creates queue entry (if enabled)
- [ ] Deleting a CMS block creates delete queue entry
- [ ] Inactive blocks are excluded (if configured)

### Category Sync

- [ ] Saving a category creates queue entry
- [ ] Root categories are excluded (level < minimum)
- [ ] Deleting a category creates delete queue entry
- [ ] Inactive categories are excluded (if configured)

### Promotion Sync

- [ ] Saving a cart rule creates queue entry
- [ ] Saving a catalog rule creates queue entry
- [ ] Inactive rules are excluded
- [ ] Expired rules are excluded

---

## CLI Command Testing

### Sync Commands

```bash
# Test each command
bin/magento ragsync:sync:products
bin/magento ragsync:sync:products --ids=1,2,3
bin/magento ragsync:sync:products --store=1
bin/magento ragsync:sync:cms
bin/magento ragsync:sync:cms --type=pages
bin/magento ragsync:sync:cms --type=blocks
bin/magento ragsync:sync:categories
bin/magento ragsync:sync:categories --ids=10,20
bin/magento ragsync:sync:promotions
bin/magento ragsync:sync:promotions --type=cart
```

- [ ] Commands execute without errors
- [ ] Output shows correct counts
- [ ] Queue entries are created

### Queue Commands

```bash
bin/magento ragsync:queue:status
bin/magento ragsync:queue:process
bin/magento ragsync:queue:clear --status=sent --force
```

- [ ] Status command shows accurate statistics
- [ ] Process command processes pending items
- [ ] Clear command removes items correctly
- [ ] Confirmation prompts work (without --force)

### Test Command

```bash
bin/magento ragsync:test:connection
```

- [ ] Shows configuration details
- [ ] Reports success when backend is available
- [ ] Reports failure with error when backend is unavailable

---

## Cron Job Testing

- [ ] Cron jobs are registered: `bin/magento cron:run --group=default`
- [ ] Queue processing runs every minute
- [ ] Full syncs run at scheduled times
- [ ] Cleanup job removes old sent items
- [ ] Reset job handles stuck processing items

---

## Error Handling Testing

### Network Errors

- [ ] Connection timeout is handled gracefully
- [ ] Network errors increment retry count
- [ ] Items are marked failed after error
- [ ] Error message is recorded

### Backend Errors

- [ ] HTTP 400 errors don't trigger circuit breaker
- [ ] HTTP 500 errors trigger circuit breaker after threshold
- [ ] Circuit breaker opens after 5 consecutive failures
- [ ] Circuit breaker half-opens after timeout

### Retry Logic

- [ ] Failed items are retried with configured delays
- [ ] Items are marked dead after max retries
- [ ] Dead items can be manually retried

---

## Performance Testing

### Single Item Sync

- [ ] Product save takes < 100ms additional time
- [ ] No noticeable delay in admin panel

### Bulk Operations

- [ ] Full product sync (1000 products) queues in < 2 minutes
- [ ] Queue processing 50 items takes < 30 seconds
- [ ] No memory issues during bulk operations

### Database

- [ ] Queue table uses indexes efficiently
- [ ] No slow queries in query log
- [ ] Cleanup job maintains reasonable table size

---

## Security Testing

- [ ] API Secret is stored encrypted in database
- [ ] API Secret is not logged
- [ ] Webhook signature is validated on backend
- [ ] Admin ACL restricts unauthorized access

---

## Browser Compatibility

- [ ] Dashboard works in Chrome
- [ ] Dashboard works in Firefox
- [ ] Dashboard works in Safari
- [ ] Dashboard works in Edge
- [ ] AJAX actions work correctly

---

## Upgrade Testing

- [ ] Module upgrades without data loss
- [ ] Configuration persists after upgrade
- [ ] Queue items persist after upgrade

---

## Sign-Off

| Test Phase | Tester | Date | Pass/Fail |
|------------|--------|------|-----------|
| Installation | | | |
| Configuration | | | |
| Dashboard | | | |
| Queue Grid | | | |
| Entity Sync | | | |
| CLI Commands | | | |
| Cron Jobs | | | |
| Error Handling | | | |
| Performance | | | |
| Security | | | |

**Final Approval:**

- [ ] All critical tests pass
- [ ] All major tests pass
- [ ] Known issues documented
- [ ] Ready for deployment

Approved by: _________________ Date: _________________
