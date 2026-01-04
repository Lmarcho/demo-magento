<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\WebhookSender;
use Psr\Log\LoggerInterface;

class TestConnectionCommand extends Command
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var WebhookSender
     */
    private WebhookSender $webhookSender;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param WebhookSender $webhookSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        WebhookSender $webhookSender,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->webhookSender = $webhookSender;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('ragsync:test:connection')
            ->setDescription('Test connection to RAG backend');
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
        $output->writeln('<info>Testing RAG Backend Connection...</info>');
        $output->writeln('');

        // Check module status
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>RAG Sync module is disabled.</error>');
            return Command::FAILURE;
        }

        // Display configuration
        $output->writeln('<comment>Configuration:</comment>');
        $output->writeln(sprintf('  Environment: %s', $this->config->getEnvironment()));
        $output->writeln(sprintf('  Webhook URL: %s', $this->config->getWebhookUrl() ?: '<error>Not configured</error>'));
        $output->writeln(sprintf('  Tenant ID: %s', $this->config->getTenantId() ?: '<error>Not configured</error>'));
        $output->writeln(sprintf('  Timeout: %d seconds', $this->config->getConnectionTimeout()));
        $output->writeln('');

        // Validate configuration
        if (empty($this->config->getWebhookUrl())) {
            $output->writeln('<error>Webhook URL is not configured.</error>');
            return Command::FAILURE;
        }

        if (empty($this->config->getApiSecret())) {
            $output->writeln('<error>API Secret is not configured.</error>');
            return Command::FAILURE;
        }

        // Send test ping
        $output->writeln('<info>Sending test ping...</info>');

        try {
            $response = $this->webhookSender->testConnection();

            if ($response->isSuccess()) {
                $output->writeln('<info>✓ Connection successful!</info>');
                $output->writeln(sprintf('  Response code: %d', $response->getStatusCode()));
                $output->writeln(sprintf('  Response time: %dms', $response->getDurationMs()));

                $responseData = $response->getBody();
                if (!empty($responseData)) {
                    $output->writeln('  Response data:');
                    foreach ($responseData as $key => $value) {
                        if (is_scalar($value)) {
                            $output->writeln(sprintf('    %s: %s', $key, $value));
                        }
                    }
                }

                return Command::SUCCESS;
            } else {
                $output->writeln('<error>✗ Connection failed!</error>');
                $output->writeln(sprintf('  Status code: %d', $response->getStatusCode()));
                $output->writeln(sprintf('  Error: %s', $response->getErrorMessage()));

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Connection failed!</error>');
            $output->writeln(sprintf('  Error: %s', $e->getMessage()));
            $this->logger->error('RagSync CLI: Test connection error', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
