<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Console\Command;

use Lmarcho\CommerceMcp\Model\Authentication\ClientManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RotateTokenCommand extends Command
{
    public function __construct(private readonly ClientManager $clientManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commerce-mcp:token:rotate')
            ->setDescription('Revoke active tokens and issue a new token for a client.')
            ->addArgument('name', InputArgument::REQUIRED, 'Client name')
            ->addOption('expires-at', null, InputOption::VALUE_REQUIRED, 'UTC expiry: YYYY-MM-DD HH:MM:SS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = $this->clientManager->rotate(
            (string)$input->getArgument('name'),
            $input->getOption('expires-at') !== null ? (string)$input->getOption('expires-at') : null
        );
        $output->writeln('<info>Token rotated. Store this token now; it will not be shown again:</info>');
        $output->writeln($token);
        return Command::SUCCESS;
    }
}
