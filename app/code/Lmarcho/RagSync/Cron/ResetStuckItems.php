<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Cron;

use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Psr\Log\LoggerInterface;

class ResetStuckItems
{
    private const STUCK_THRESHOLD_MINUTES = 30;

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
     * Execute reset stuck items
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $resetCount = $this->queueResource->resetStuckItems(self::STUCK_THRESHOLD_MINUTES);

        if ($resetCount > 0) {
            $this->logger->warning('RagSync: Reset stuck items', [
                'reset_count' => $resetCount,
                'threshold_minutes' => self::STUCK_THRESHOLD_MINUTES,
            ]);
        }
    }
}
