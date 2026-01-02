<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Psr\Log\LoggerInterface;

class CleanupQueue
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param QueueResource $queueResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        QueueResource $queueResource,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->queueResource = $queueResource;
        $this->logger = $logger;
    }

    /**
     * Execute queue cleanup
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $cleanupDays = $this->config->getQueueCleanupDays();
        $deletedCount = $this->queueResource->cleanupOldItems($cleanupDays);

        if ($deletedCount > 0) {
            $this->logger->info('RagSync: Queue cleanup completed', [
                'deleted_count' => $deletedCount,
                'older_than_days' => $cleanupDays,
            ]);
        }
    }
}
