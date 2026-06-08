<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Authentication;

class AuthenticatedClient
{
    /**
     * @param string[] $allowedTools
     */
    public function __construct(
        private readonly int $clientId,
        private readonly string $clientName,
        private readonly array $allowedTools
    ) {
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    /**
     * @return string[]
     */
    public function getAllowedTools(): array
    {
        return $this->allowedTools;
    }
}
