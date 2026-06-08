<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class ProtocolNegotiator
{
    public const PRIMARY_VERSION = '2025-11-25';
    private const SUPPORTED_VERSIONS = [self::PRIMARY_VERSION];

    public function negotiate(?string $requestedVersion, mixed $requestId): string
    {
        if ($requestedVersion === null || !in_array($requestedVersion, self::SUPPORTED_VERSIONS, true)) {
            throw new JsonRpcException(
                'Unsupported protocol version',
                -32602,
                $requestId,
                ['supported' => self::SUPPORTED_VERSIONS]
            );
        }

        return $requestedVersion;
    }

    /**
     * @return string[]
     */
    public function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }
}
