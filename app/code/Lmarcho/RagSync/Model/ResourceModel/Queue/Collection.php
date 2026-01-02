<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model\ResourceModel\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Lmarcho\RagSync\Model\Queue as QueueModel;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'rag_sync_queue_collection';

    /**
     * @var string
     */
    protected $_eventObject = 'queue_collection';

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(QueueModel::class, QueueResource::class);
    }

    /**
     * Filter by status
     *
     * @param string|array $status
     * @return $this
     */
    public function addStatusFilter($status): self
    {
        if (is_array($status)) {
            $this->addFieldToFilter('status', ['in' => $status]);
        } else {
            $this->addFieldToFilter('status', $status);
        }
        return $this;
    }

    /**
     * Filter by entity type
     *
     * @param string|array $entityType
     * @return $this
     */
    public function addEntityTypeFilter($entityType): self
    {
        if (is_array($entityType)) {
            $this->addFieldToFilter('entity_type', ['in' => $entityType]);
        } else {
            $this->addFieldToFilter('entity_type', $entityType);
        }
        return $this;
    }

    /**
     * Filter by store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function addStoreFilter(int $storeId): self
    {
        $this->addFieldToFilter('store_id', $storeId);
        return $this;
    }

    /**
     * Filter pending items
     *
     * @return $this
     */
    public function addPendingFilter(): self
    {
        return $this->addStatusFilter(QueueModel::STATUS_PENDING);
    }

    /**
     * Filter failed items
     *
     * @return $this
     */
    public function addFailedFilter(): self
    {
        return $this->addStatusFilter(QueueModel::STATUS_FAILED);
    }

    /**
     * Filter dead items
     *
     * @return $this
     */
    public function addDeadFilter(): self
    {
        return $this->addStatusFilter(QueueModel::STATUS_DEAD);
    }

    /**
     * Order by priority (ascending - lower number = higher priority)
     *
     * @return $this
     */
    public function orderByPriority(): self
    {
        $this->setOrder('priority', self::SORT_ORDER_ASC);
        $this->setOrder('created_at', self::SORT_ORDER_ASC);
        return $this;
    }

    /**
     * Filter items older than specified date
     *
     * @param string $date
     * @return $this
     */
    public function addOlderThanFilter(string $date): self
    {
        $this->addFieldToFilter('updated_at', ['lteq' => $date]);
        return $this;
    }

    /**
     * Get items for processing (pending, ordered by priority)
     *
     * @param int $limit
     * @return $this
     */
    public function getItemsForProcessing(int $limit = 50): self
    {
        $this->addPendingFilter()
            ->orderByPriority()
            ->setPageSize($limit);
        return $this;
    }

    /**
     * Get count by status
     *
     * @return array
     */
    public function getCountByStatus(): array
    {
        $connection = $this->getConnection();

        $select = $this->getSelect()
            ->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'status',
                'count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->group('status');

        return $connection->fetchPairs($select);
    }

    /**
     * Get count by entity type
     *
     * @return array
     */
    public function getCountByEntityType(): array
    {
        $connection = $this->getConnection();

        $select = $this->getSelect()
            ->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns([
                'entity_type',
                'count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->group('entity_type');

        return $connection->fetchPairs($select);
    }
}
