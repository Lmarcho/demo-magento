<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Console\Command;

use Lmarcho\CommerceMcp\Model\Authentication\ClientManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RevokeClientCommand extends Command
{
    public function __construct(private readonly ClientManager $clientManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commerce-mcp:client:revoke')
            ->setDescription('Disable a Commerce MCP client and revoke all its tokens.')
            ->addArgument('name', InputArgument::REQUIRED, 'Client name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->clientManager->revoke((string)$input->getArgument('name'));
        $output->writeln('<info>Client revoked.</info>');
        return Command::SUCCESS;
    }
}
