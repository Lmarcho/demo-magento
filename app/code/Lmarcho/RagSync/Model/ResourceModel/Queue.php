<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Lmarcho\RagSync\Model\Queue as QueueModel;

class Queue extends AbstractDb
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->dateTime = $dateTime;
    }

    /**
     * Initialize resource
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('rag_sync_queue', 'id');
    }

    /**
     * Add or update queue item (upsert with deduplication)
     *
     * @param string $entityType
     * @param string $entityId
     * @param int $storeId
     * @param string $action
     * @param int|null $priority
     * @return int The ID of the inserted/updated record
     */
    public function addToQueue(
        string $entityType,
        string $entityId,
        int $storeId,
        string $action,
        ?int $priority = null
    ): int {
        $connection = $this->getConnection();
        $table = $this->getMainTable();
        $now = $this->dateTime->gmtDate();

        $priority = $priority ?? QueueModel::getPriorityForEntityType($entityType, $action);

        $data = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'store_id' => $storeId,
            'action' => $action,
            'priority' => $priority,
            'status' => QueueModel::STATUS_PENDING,
            'attempts' => 0,
            'error_message' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Use INSERT ... ON DUPLICATE KEY UPDATE for deduplication
        // Reset attempts and error_message so previously failed items can be retried fresh
        $connection->insertOnDuplicate(
            $table,
            $data,
            ['updated_at', 'status', 'priority', 'attempts', 'error_message']
        );

        // Get the ID of the record
        $select = $connection->select()
            ->from($table, ['id'])
            ->where('entity_type = ?', $entityType)
            ->where('entity_id = ?', $entityId)
            ->where('store_id = ?', $storeId)
            ->where('action = ?', $action);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Get pending items for processing
     *
     * @param int $limit
     * @return array
     */
    public function getPendingItems(int $limit = 50): array
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $select = $connection->select()
            ->from($table)
            ->where('status = ?', QueueModel::STATUS_PENDING)
            ->order(['priority ASC', 'created_at ASC'])
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Get items ready for retry
     *
     * @param array $retryDelays Array of delays in minutes [5, 15, 60]
     * @param int $limit
     * @return array
     */
    public function getItemsForRetry(array $retryDelays, int $limit = 50): array
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();
        $now = $this->dateTime->gmtDate();

        // Build conditions for each retry attempt
        $conditions = [];
        foreach ($retryDelays as $attempt => $delayMinutes) {
            $attemptNum = $attempt + 1;
            $conditions[] = sprintf(
                "(attempts = %d AND last_attempt_at <= DATE_SUB('%s', INTERVAL %d MINUTE))",
                $attemptNum,
                $now,
                $delayMinutes
            );
        }

        if (empty($conditions)) {
            return [];
        }

        $select = $connection->select()
            ->from($table)
            ->where('status = ?', QueueModel::STATUS_FAILED)
            ->where(implode(' OR ', $conditions))
            ->order(['priority ASC', 'created_at ASC'])
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Update item status
     *
     * @param int $id
     * @param string $status
     * @param string|null $errorMessage
     * @return int Number of affected rows
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $data = [
            'status' => $status,
            'updated_at' => $this->dateTime->gmtDate(),
        ];

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        return $connection->update($table, $data, ['id = ?' => $id]);
    }

    /**
     * Mark items as processing (lock them)
     *
     * @param array $ids
     * @return int Number of affected rows
     */
    public function markAsProcessing(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $connection = $this->getConnection();
        $table = $this->getMainTable();

        return $connection->update(
            $table,
            [
                'status' => QueueModel::STATUS_PROCESSING,
                'last_attempt_at' => $this->dateTime->gmtDate(),
                'attempts' => new \Zend_Db_Expr('attempts + 1'),
                'updated_at' => $this->dateTime->gmtDate(),
            ],
            ['id IN (?)' => $ids]
        );
    }

    /**
     * Mark items as sent (success)
     *
     * @param array $ids
     * @return int Number of affected rows
     */
    public function markAsSent(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $connection = $this->getConnection();
        $table = $this->getMainTable();

        return $connection->update(
            $table,
            [
                'status' => QueueModel::STATUS_SENT,
                'error_message' => null,
                'updated_at' => $this->dateTime->gmtDate(),
            ],
            ['id IN (?)' => $ids]
        );
    }

    /**
     * Mark items as failed
     *
     * @param array $ids
     * @param string $errorMessage
     * @param int $maxRetries
     * @return int Number of affected rows
     */
    public function markAsFailed(array $ids, string $errorMessage, int $maxRetries = 3): int
    {
        if (empty($ids)) {
            return 0;
        }

        $connection = $this->getConnection();
        $table = $this->getMainTable();

        // First, get the current attempts for these items
        $select = $connection->select()
            ->from($table, ['id', 'attempts'])
            ->where('id IN (?)', $ids);

        $items = $connection->fetchPairs($select);
        $failedIds = [];
        $deadIds = [];

        foreach ($items as $id => $attempts) {
            if ($attempts >= $maxRetries) {
                $deadIds[] = $id;
            } else {
                $failedIds[] = $id;
            }
        }

        $affectedRows = 0;

        if (!empty($failedIds)) {
            $affectedRows += $connection->update(
                $table,
                [
                    'status' => QueueModel::STATUS_FAILED,
                    'error_message' => $errorMessage,
                    'updated_at' => $this->dateTime->gmtDate(),
                ],
                ['id IN (?)' => $failedIds]
            );
        }

        if (!empty($deadIds)) {
            $affectedRows += $connection->update(
                $table,
                [
                    'status' => QueueModel::STATUS_DEAD,
                    'error_message' => $errorMessage,
                    'updated_at' => $this->dateTime->gmtDate(),
                ],
                ['id IN (?)' => $deadIds]
            );
        }

        return $affectedRows;
    }

    /**
     * Cleanup old sent items
     *
     * @param int $days
     * @return int Number of deleted rows
     */
    public function cleanupOldItems(int $days = 7): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();
        $cutoffTimestamp = $this->dateTime->gmtTimestamp() - ($days * 86400);
        $cutoffDate = date('Y-m-d H:i:s', $cutoffTimestamp);

        return $connection->delete(
            $table,
            [
                'status = ?' => QueueModel::STATUS_SENT,
                'updated_at <= ?' => $cutoffDate,
            ]
        );
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $select = $connection->select()
            ->from($table, [
                'status',
                'count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->group('status');

        $stats = $connection->fetchPairs($select);

        return [
            'pending' => (int)($stats[QueueModel::STATUS_PENDING] ?? 0),
            'processing' => (int)($stats[QueueModel::STATUS_PROCESSING] ?? 0),
            'sent' => (int)($stats[QueueModel::STATUS_SENT] ?? 0),
            'failed' => (int)($stats[QueueModel::STATUS_FAILED] ?? 0),
            'dead' => (int)($stats[QueueModel::STATUS_DEAD] ?? 0),
            'total' => array_sum($stats),
        ];
    }

    /**
     * Get oldest pending item age in minutes
     *
     * @return int|null
     */
    public function getOldestPendingAgeMinutes(): ?int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $select = $connection->select()
            ->from($table, ['created_at'])
            ->where('status = ?', QueueModel::STATUS_PENDING)
            ->order('created_at ASC')
            ->limit(1);

        $oldestDate = $connection->fetchOne($select);

        if (!$oldestDate) {
            return null;
        }

        $now = strtotime($this->dateTime->gmtDate());
        $oldest = strtotime($oldestDate);

        return (int)(($now - $oldest) / 60);
    }

    /**
     * Reset stuck processing items (older than X minutes)
     *
     * @param int $minutes
     * @return int Number of reset items
     */
    public function resetStuckItems(int $minutes = 30): int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();
        $cutoffTimestamp = $this->dateTime->gmtTimestamp() - ($minutes * 60);
        $cutoffDate = date('Y-m-d H:i:s', $cutoffTimestamp);

        return $connection->update(
            $table,
            [
                'status' => QueueModel::STATUS_PENDING,
                'updated_at' => $this->dateTime->gmtDate(),
            ],
            [
                'status = ?' => QueueModel::STATUS_PROCESSING,
                'last_attempt_at <= ?' => $cutoffDate,
            ]
        );
    }
}
