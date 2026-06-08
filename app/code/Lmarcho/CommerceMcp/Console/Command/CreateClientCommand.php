<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Console\Command;

use Lmarcho\CommerceMcp\Model\Authentication\ClientManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateClientCommand extends Command
{
    public function __construct(private readonly ClientManager $clientManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commerce-mcp:client:create')
            ->setDescription('Create a named Commerce MCP client and show its token once.')
            ->addArgument('name', InputArgument::REQUIRED, 'Client name')
            ->addOption('expires-at', null, InputOption::VALUE_REQUIRED, 'UTC expiry: YYYY-MM-DD HH:MM:SS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = $this->clientManager->create(
            (string)$input->getArgument('name'),
            $input->getOption('expires-at') !== null ? (string)$input->getOption('expires-at') : null
        );
        $output->writeln('<info>Client created. Store this token now; it will not be shown again:</info>');
        $output->writeln($token);
        return Command::SUCCESS;
    }
}
