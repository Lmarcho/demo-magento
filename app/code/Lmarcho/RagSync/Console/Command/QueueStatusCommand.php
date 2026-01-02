<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Lmarcho\RagSync\Model\Config;

class QueueStatusCommand extends Command
{
    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param QueueResource $queueResource
     * @param Config $config
     */
    public function __construct(
        QueueResource $queueResource,
        Config $config
    ) {
        $this->queueResource = $queueResource;
        $this->config = $config;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('ragsync:queue:status')
            ->setDescription('Display queue status and statistics');
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>RAG Sync Queue Status</info>');
        $output->writeln('');

        // Module status
        $moduleStatus = $this->config->isEnabled() ? '<info>Enabled</info>' : '<error>Disabled</error>';
        $output->writeln(sprintf('Module Status: %s', $moduleStatus));
        $output->writeln('');

        // Get queue statistics
        $stats = $this->queueResource->getQueueStats();

        // Status breakdown
        $output->writeln('<comment>Queue Status Breakdown:</comment>');
        $table = new Table($output);
        $table->setHeaders(['Status', 'Count']);

        $statusRows = [
            ['Pending', $stats['pending'] ?? 0],
            ['Processing', $stats['processing'] ?? 0],
            ['Sent', $stats['sent'] ?? 0],
            ['Failed', $stats['failed'] ?? 0],
            ['Dead', $stats['dead'] ?? 0],
            ['<info>Total</info>', '<info>' . ($stats['total'] ?? 0) . '</info>'],
        ];

        $table->setRows($statusRows);
        $table->render();

        $output->writeln('');

        // Entity type breakdown
        $output->writeln('<comment>Entity Type Breakdown:</comment>');
        $entityStats = $this->getEntityTypeStats();

        $entityTable = new Table($output);
        $entityTable->setHeaders(['Entity Type', 'Pending', 'Failed']);
        $entityTable->setRows($entityStats);
        $entityTable->render();

        $output->writeln('');

        // Configuration info
        $output->writeln('<comment>Configuration:</comment>');
        $output->writeln(sprintf('  Webhook URL: %s', $this->config->getWebhookUrl() ?: '<error>Not configured</error>'));
        $output->writeln(sprintf('  Batch Size: %d', $this->config->getBatchSize()));
        $output->writeln(sprintf('  Max Retries: %d', $this->config->getMaxRetries()));
        $output->writeln(sprintf('  Environment: %s', $this->config->getEnvironment()));

        return Command::SUCCESS;
    }

    /**
     * Get entity type statistics
     *
     * @return array
     */
    private function getEntityTypeStats(): array
    {
        $connection = $this->queueResource->getConnection();
        $tableName = $this->queueResource->getMainTable();

        $select = $connection->select()
            ->from($tableName, [
                'entity_type',
                'pending' => new \Zend_Db_Expr("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)"),
                'failed' => new \Zend_Db_Expr("SUM(CASE WHEN status IN ('failed', 'dead') THEN 1 ELSE 0 END)"),
            ])
            ->group('entity_type');

        $results = $connection->fetchAll($select);

        $stats = [];
        foreach ($results as $row) {
            $stats[] = [
                $row['entity_type'],
                $row['pending'],
                $row['failed'],
            ];
        }

        return $stats;
    }
}
