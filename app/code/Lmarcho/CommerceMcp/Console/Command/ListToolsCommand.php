<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Console\Command;

use Lmarcho\CommerceMcp\Model\Mcp\ToolRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListToolsCommand extends Command
{
    public function __construct(private readonly ToolRegistry $toolRegistry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commerce-mcp:tools:list')
            ->setDescription('List the approved Commerce MCP tools.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->toolRegistry->names() as $toolName) {
            $output->writeln($toolName);
        }
        return Command::SUCCESS;
    }
}
