<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Api;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return array<string,mixed>
     */
    public function getInputSchema(): array;

    /**
     * @param array<string,mixed> $arguments
     * @return array<string,mixed>
     */
    public function execute(array $arguments): array;
}
