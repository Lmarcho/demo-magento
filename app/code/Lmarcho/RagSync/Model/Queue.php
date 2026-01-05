<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

use Magento\Framework\Model\AbstractModel;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;

class Queue extends AbstractModel
{
    // Entity types
    public const ENTITY_TYPE_PRODUCT = 'product';
    public const ENTITY_TYPE_CMS_PAGE = 'cms_page';
    public const ENTITY_TYPE_CMS_BLOCK = 'cms_block';
    public const ENTITY_TYPE_CATEGORY = 'category';
    public const ENTITY_TYPE_PROMOTION = 'promotion';
    public const ENTITY_TYPE_CATALOG_RULE = 'catalog_rule';
    public const ENTITY_TYPE_STORE_CONFIG = 'store_config';

    // Actions
    public const ACTION_SAVE = 'save';
    public const ACTION_DELETE = 'delete';

    // Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';

    // Priorities (1 = highest, 10 = lowest)
    public const PRIORITY_DELETE = 1;
    public const PRIORITY_STORE_CONFIG = 1;
    public const PRIORITY_PRODUCT = 2;
    public const PRIORITY_CMS_PAGE = 3;
    public const PRIORITY_CATEGORY = 4;
    public const PRIORITY_PROMOTION = 5;
    public const PRIORITY_CMS_BLOCK = 7;
    public const PRIORITY_ATTRIBUTE = 10;

    /**
     * @var string
     */
    protected $_eventPrefix = 'rag_sync_queue';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(QueueResource::class);
    }

    /**
     * Get entity type
     *
     * @return string|null
     */
    public function getEntityType(): ?string
    {
        return $this->getData('entity_type');
    }

    /**
     * Set entity type
     *
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self
    {
        return $this->setData('entity_type', $entityType);
    }

    /**
     * Get sync entity ID (not to be confused with model entity ID)
     *
     * @return string|null
     */
    public function getSyncEntityId(): ?string
    {
        return $this->getData('entity_id');
    }

    /**
     * Set sync entity ID (not to be confused with model entity ID)
     *
     * @param string|int $entityId
     * @return $this
     */
    public function setSyncEntityId($entityId): self
    {
        return $this->setData('entity_id', (string)$entityId);
    }

    /**
     * Get store ID
     *
     * @return int
     */
    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    /**
     * Set store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self
    {
        return $this->setData('store_id', $storeId);
    }

    /**
     * Get action
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->getData('action');
    }

    /**
     * Set action
     *
     * @param string $action
     * @return $this
     */
    public function setAction(string $action): self
    {
        return $this->setData('action', $action);
    }

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority(): int
    {
        return (int)$this->getData('priority');
    }

    /**
     * Set priority
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority(int $priority): self
    {
        return $this->setData('priority', $priority);
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getData('status') ?? self::STATUS_PENDING;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Get number of attempts
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return (int)$this->getData('attempts');
    }

    /**
     * Set number of attempts
     *
     * @param int $attempts
     * @return $this
     */
    public function setAttempts(int $attempts): self
    {
        return $this->setData('attempts', $attempts);
    }

    /**
     * Increment attempts
     *
     * @return $this
     */
    public function incrementAttempts(): self
    {
        return $this->setAttempts($this->getAttempts() + 1);
    }

    /**
     * Get last attempt timestamp
     *
     * @return string|null
     */
    public function getLastAttemptAt(): ?string
    {
        return $this->getData('last_attempt_at');
    }

    /**
     * Set last attempt timestamp
     *
     * @param string $timestamp
     * @return $this
     */
    public function setLastAttemptAt(string $timestamp): self
    {
        return $this->setData('last_attempt_at', $timestamp);
    }

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    /**
     * Set error message
     *
     * @param string|null $message
     * @return $this
     */
    public function setErrorMessage(?string $message): self
    {
        return $this->setData('error_message', $message);
    }

    /**
     * Mark as processing
     *
     * @return $this
     */
    public function markAsProcessing(): self
    {
        $this->setStatus(self::STATUS_PROCESSING);
        $this->setLastAttemptAt(date('Y-m-d H:i:s'));
        $this->incrementAttempts();
        return $this;
    }

    /**
     * Mark as sent (success)
     *
     * @return $this
     */
    public function markAsSent(): self
    {
        $this->setStatus(self::STATUS_SENT);
        $this->setErrorMessage(null);
        return $this;
    }

    /**
     * Mark as failed
     *
     * @param string $errorMessage
     * @param int $maxRetries
     * @return $this
     */
    public function markAsFailed(string $errorMessage, int $maxRetries = 3): self
    {
        $this->setErrorMessage($errorMessage);

        if ($this->getAttempts() >= $maxRetries) {
            $this->setStatus(self::STATUS_DEAD);
        } else {
            $this->setStatus(self::STATUS_FAILED);
        }

        return $this;
    }

    /**
     * Check if can be retried
     *
     * @param int $maxRetries
     * @return bool
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->getAttempts() < $maxRetries
            && in_array($this->getStatus(), [self::STATUS_FAILED, self::STATUS_PENDING]);
    }

    /**
     * Reset for retry
     *
     * @return $this
     */
    public function resetForRetry(): self
    {
        $this->setStatus(self::STATUS_PENDING);
        return $this;
    }

    /**
     * Get priority for entity type
     *
     * @param string $entityType
     * @param string $action
     * @return int
     */
    public static function getPriorityForEntityType(string $entityType, string $action = self::ACTION_SAVE): int
    {
        // Delete operations always have highest priority
        if ($action === self::ACTION_DELETE) {
            return self::PRIORITY_DELETE;
        }

        $priorities = [
            self::ENTITY_TYPE_STORE_CONFIG => self::PRIORITY_STORE_CONFIG,
            self::ENTITY_TYPE_PRODUCT => self::PRIORITY_PRODUCT,
            self::ENTITY_TYPE_CMS_PAGE => self::PRIORITY_CMS_PAGE,
            self::ENTITY_TYPE_CATEGORY => self::PRIORITY_CATEGORY,
            self::ENTITY_TYPE_PROMOTION => self::PRIORITY_PROMOTION,
            self::ENTITY_TYPE_CATALOG_RULE => self::PRIORITY_PROMOTION,
            self::ENTITY_TYPE_CMS_BLOCK => self::PRIORITY_CMS_BLOCK,
        ];

        return $priorities[$entityType] ?? 5;
    }
}
