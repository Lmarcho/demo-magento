<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Lmarcho\RagSync\Model\ResourceModel\Queue as QueueResource;
use Lmarcho\RagSync\Model\Queue;
use Psr\Log\LoggerInterface;

class QueueClearCommand extends Command
{
    /**
     * @var QueueResource
     */
    private QueueResource $queueResource;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param QueueResource $queueResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        QueueResource $queueResource,
        LoggerInterface $logger
    ) {
        $this->queueResource = $queueResource;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('ragsync:queue:clear')
            ->setDescription('Clear queue items by status')
            ->addOption(
                'status',
                's',
                InputOption::VALUE_OPTIONAL,
                'Status to clear: sent, failed, dead, all (default: sent)',
                'sent'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            );
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
        $status = $input->getOption('status');
        $force = $input->getOption('force');

        $validStatuses = ['sent', 'failed', 'dead', 'all'];
        if (!in_array($status, $validStatuses)) {
            $output->writeln(sprintf(
                '<error>Invalid status "%s". Valid options: %s</error>',
                $status,
                implode(', ', $validStatuses)
            ));
            return Command::FAILURE;
        }

        // Confirmation
        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('<question>Are you sure you want to clear "%s" queue items? (y/N)</question> ', $status),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        try {
            $connection = $this->queueResource->getConnection();
            $tableName = $this->queueResource->getMainTable();

            if ($status === 'all') {
                $count = $connection->delete($tableName);
            } else {
                $statusMap = [
                    'sent' => Queue::STATUS_SENT,
                    'failed' => Queue::STATUS_FAILED,
                    'dead' => Queue::STATUS_DEAD,
                ];

                $count = $connection->delete(
                    $tableName,
                    ['status = ?' => $statusMap[$status]]
                );
            }

            $output->writeln(sprintf(
                '<info>Successfully cleared %d queue items with status "%s".</info>',
                $count,
                $status
            ));

            $this->logger->info('RagSync CLI: Queue cleared', [
                'status' => $status,
                'count' => $count,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: Queue clear error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
