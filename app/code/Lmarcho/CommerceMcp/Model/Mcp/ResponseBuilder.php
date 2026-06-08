<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

class ResponseBuilder
{
    public function success(mixed $id, array $result, string $correlationId): array
    {
        $result['_meta']['correlation_id'] = $correlationId;

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    public function error(
        mixed $id,
        int $code,
        string $message,
        string $correlationId,
        array $data = []
    ): array {
        $data['correlation_id'] = $correlationId;

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => $data,
            ],
        ];
    }
}
