<?php
/**
 * Lmarcho RagSync Module - Queue Model Unit Tests
 *
 * Note: This test focuses on static methods and constants that don't require
 * Magento's ObjectManager. Instance methods require integration tests.
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Unit\Model;

use Lmarcho\RagSync\Model\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', Queue::STATUS_PENDING);
        $this->assertEquals('processing', Queue::STATUS_PROCESSING);
        $this->assertEquals('sent', Queue::STATUS_SENT);
        $this->assertEquals('failed', Queue::STATUS_FAILED);
        $this->assertEquals('dead', Queue::STATUS_DEAD);
    }

    public function testEntityTypeConstants(): void
    {
        $this->assertEquals('product', Queue::ENTITY_TYPE_PRODUCT);
        $this->assertEquals('cms_page', Queue::ENTITY_TYPE_CMS_PAGE);
        $this->assertEquals('cms_block', Queue::ENTITY_TYPE_CMS_BLOCK);
        $this->assertEquals('category', Queue::ENTITY_TYPE_CATEGORY);
        $this->assertEquals('promotion', Queue::ENTITY_TYPE_PROMOTION);
        $this->assertEquals('catalog_rule', Queue::ENTITY_TYPE_CATALOG_RULE);
    }

    public function testActionConstants(): void
    {
        $this->assertEquals('save', Queue::ACTION_SAVE);
        $this->assertEquals('delete', Queue::ACTION_DELETE);
    }

    public function testPriorityConstants(): void
    {
        $this->assertEquals(1, Queue::PRIORITY_DELETE);
        $this->assertEquals(2, Queue::PRIORITY_PRODUCT);
        $this->assertEquals(3, Queue::PRIORITY_CMS_PAGE);
        $this->assertEquals(4, Queue::PRIORITY_CATEGORY);
        $this->assertEquals(5, Queue::PRIORITY_PROMOTION);
        $this->assertEquals(7, Queue::PRIORITY_CMS_BLOCK);
        $this->assertEquals(10, Queue::PRIORITY_ATTRIBUTE);
    }

    public function testGetPriorityForEntityTypeDeleteActions(): void
    {
        // Delete operations always have highest priority
        $this->assertEquals(1, Queue::getPriorityForEntityType('product', Queue::ACTION_DELETE));
        $this->assertEquals(1, Queue::getPriorityForEntityType('cms_page', Queue::ACTION_DELETE));
        $this->assertEquals(1, Queue::getPriorityForEntityType('category', Queue::ACTION_DELETE));
        $this->assertEquals(1, Queue::getPriorityForEntityType('unknown', Queue::ACTION_DELETE));
    }

    public function testGetPriorityForEntityTypeSaveActions(): void
    {
        // Products have priority 2
        $this->assertEquals(2, Queue::getPriorityForEntityType('product', Queue::ACTION_SAVE));

        // CMS pages have priority 3
        $this->assertEquals(3, Queue::getPriorityForEntityType('cms_page', Queue::ACTION_SAVE));

        // Categories have priority 4
        $this->assertEquals(4, Queue::getPriorityForEntityType('category', Queue::ACTION_SAVE));

        // Promotions have priority 5
        $this->assertEquals(5, Queue::getPriorityForEntityType('promotion', Queue::ACTION_SAVE));
        $this->assertEquals(5, Queue::getPriorityForEntityType('catalog_rule', Queue::ACTION_SAVE));

        // CMS blocks have priority 7
        $this->assertEquals(7, Queue::getPriorityForEntityType('cms_block', Queue::ACTION_SAVE));

        // Unknown types default to 5
        $this->assertEquals(5, Queue::getPriorityForEntityType('unknown', Queue::ACTION_SAVE));
    }

    public function testGetPriorityForEntityTypeDefaultAction(): void
    {
        // Without specifying action, should default to SAVE action priorities
        $this->assertEquals(2, Queue::getPriorityForEntityType('product'));
        $this->assertEquals(3, Queue::getPriorityForEntityType('cms_page'));
        $this->assertEquals(4, Queue::getPriorityForEntityType('category'));
    }
}
