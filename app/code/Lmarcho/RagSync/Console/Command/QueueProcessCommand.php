<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Lmarcho\RagSync\Cron\ProcessQueue;
use Lmarcho\RagSync\Model\Config;
use Psr\Log\LoggerInterface;

class QueueProcessCommand extends Command
{
    /**
     * @var ProcessQueue
     */
    private ProcessQueue $processQueue;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProcessQueue $processQueue
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProcessQueue $processQueue,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->processQueue = $processQueue;
        $this->config = $config;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('ragsync:queue:process')
            ->setDescription('Process pending queue items and send to RAG backend');
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
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>RAG Sync module is disabled.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Processing queue...</info>');

        try {
            $this->processQueue->execute();
            $output->writeln('<info>Queue processing completed.</info>');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->error('RagSync CLI: Queue process error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
