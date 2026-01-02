<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class CircuitBreaker
{
    private const TABLE_NAME = 'rag_sync_circuit_breaker';
    private const SERVICE_NAME = 'rag_webhook';

    // Circuit states
    private const STATE_CLOSED = 'closed';     // Normal operation
    private const STATE_OPEN = 'open';         // Blocking requests
    private const STATE_HALF_OPEN = 'half_open'; // Testing if service recovered

    // Default thresholds
    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_RECOVERY_TIMEOUT = 300; // 5 minutes in seconds

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var int
     */
    private int $failureThreshold;

    /**
     * @var int
     */
    private int $recoveryTimeout;

    /**
     * @var array|null Cached state
     */
    private ?array $cachedState = null;

    /**
     * @param ResourceConnection $resource
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     * @param int $failureThreshold
     * @param int $recoveryTimeout
     */
    public function __construct(
        ResourceConnection $resource,
        DateTime $dateTime,
        LoggerInterface $logger,
        int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        int $recoveryTimeout = self::DEFAULT_RECOVERY_TIMEOUT
    ) {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
    }

    /**
     * Check if service is available (circuit is not open)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $state = $this->getState();

        if ($state['state'] === self::STATE_CLOSED) {
            return true;
        }

        if ($state['state'] === self::STATE_OPEN) {
            // Check if recovery timeout has passed
            if ($this->hasRecoveryTimeoutPassed($state)) {
                $this->transitionToHalfOpen();
                return true;
            }
            return false;
        }

        // Half-open state - allow one request through
        return true;
    }

    /**
     * Record a successful request
     *
     * @return void
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        // If in half-open state, close the circuit
        if ($state['state'] === self::STATE_HALF_OPEN) {
            $this->closeCircuit();
            $this->logger->info('RagSync: Circuit breaker closed - service recovered');
        }

        // Reset failure count on success
        if ($state['failure_count'] > 0) {
            $this->resetFailureCount();
        }
    }

    /**
     * Record a failed request
     *
     * @return void
     */
    public function recordFailure(): void
    {
        $state = $this->getState();
        $newFailureCount = $state['failure_count'] + 1;

        // If in half-open state, immediately open the circuit
        if ($state['state'] === self::STATE_HALF_OPEN) {
            $this->openCircuit($newFailureCount);
            $this->logger->warning('RagSync: Circuit breaker reopened - service still unavailable');
            return;
        }

        // Check if we've exceeded the failure threshold
        if ($newFailureCount >= $this->failureThreshold) {
            $this->openCircuit($newFailureCount);
            $this->logger->warning('RagSync: Circuit breaker opened - too many failures', [
                'failure_count' => $newFailureCount,
                'threshold' => $this->failureThreshold,
            ]);
        } else {
            $this->incrementFailureCount($newFailureCount);
        }
    }

    /**
     * Get current circuit state
     *
     * @return array
     */
    public function getState(): array
    {
        if ($this->cachedState !== null) {
            return $this->cachedState;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);

        $select = $connection->select()
            ->from($table)
            ->where('service_name = ?', self::SERVICE_NAME);

        $state = $connection->fetchRow($select);

        if (!$state) {
            // Initialize state if not exists
            $state = $this->initializeState();
        }

        $this->cachedState = $state;
        return $state;
    }

    /**
     * Force close the circuit (manual recovery)
     *
     * @return void
     */
    public function forceClose(): void
    {
        $this->closeCircuit();
        $this->logger->info('RagSync: Circuit breaker force closed');
    }

    /**
     * Get circuit status for dashboard
     *
     * @return array
     */
    public function getStatus(): array
    {
        $state = $this->getState();

        return [
            'state' => $state['state'],
            'failure_count' => (int)$state['failure_count'],
            'last_failure_at' => $state['last_failure_at'],
            'opened_at' => $state['opened_at'],
            'is_available' => $this->isAvailable(),
            'threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
        ];
    }

    /**
     * Initialize circuit state in database
     *
     * @return array
     */
    private function initializeState(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);
        $now = $this->dateTime->gmtDate();

        $data = [
            'service_name' => self::SERVICE_NAME,
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'last_failure_at' => null,
            'opened_at' => null,
            'updated_at' => $now,
        ];

        $connection->insert($table, $data);

        return $data;
    }

    /**
     * Open the circuit
     *
     * @param int $failureCount
     * @return void
     */
    private function openCircuit(int $failureCount): void
    {
        $now = $this->dateTime->gmtDate();
        $this->updateState([
            'state' => self::STATE_OPEN,
            'failure_count' => $failureCount,
            'last_failure_at' => $now,
            'opened_at' => $now,
        ]);
    }

    /**
     * Close the circuit
     *
     * @return void
     */
    private function closeCircuit(): void
    {
        $this->updateState([
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'last_failure_at' => null,
            'opened_at' => null,
        ]);
    }

    /**
     * Transition to half-open state
     *
     * @return void
     */
    private function transitionToHalfOpen(): void
    {
        $this->updateState([
            'state' => self::STATE_HALF_OPEN,
        ]);
        $this->logger->info('RagSync: Circuit breaker half-open - testing service');
    }

    /**
     * Increment failure count
     *
     * @param int $count
     * @return void
     */
    private function incrementFailureCount(int $count): void
    {
        $this->updateState([
            'failure_count' => $count,
            'last_failure_at' => $this->dateTime->gmtDate(),
        ]);
    }

    /**
     * Reset failure count
     *
     * @return void
     */
    private function resetFailureCount(): void
    {
        $this->updateState([
            'failure_count' => 0,
        ]);
    }

    /**
     * Update state in database
     *
     * @param array $data
     * @return void
     */
    private function updateState(array $data): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE_NAME);

        $data['updated_at'] = $this->dateTime->gmtDate();

        $connection->update(
            $table,
            $data,
            ['service_name = ?' => self::SERVICE_NAME]
        );

        // Invalidate cache
        $this->cachedState = null;
    }

    /**
     * Check if recovery timeout has passed
     *
     * @param array $state
     * @return bool
     */
    private function hasRecoveryTimeoutPassed(array $state): bool
    {
        if (empty($state['opened_at'])) {
            return true;
        }

        $openedAt = strtotime($state['opened_at']);
        $now = strtotime($this->dateTime->gmtDate());

        return ($now - $openedAt) >= $this->recoveryTimeout;
    }
}
