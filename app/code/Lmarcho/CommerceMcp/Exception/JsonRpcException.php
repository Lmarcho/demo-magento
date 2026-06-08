<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Exception;

use RuntimeException;

class JsonRpcException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $rpcCode,
        private readonly mixed $requestId = null,
        private readonly array $errorData = []
    ) {
        parent::__construct($message);
    }

    public function getRpcCode(): int
    {
        return $this->rpcCode;
    }

    public function getRequestId(): mixed
    {
        return $this->requestId;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
