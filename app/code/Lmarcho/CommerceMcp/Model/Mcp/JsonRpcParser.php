<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class JsonRpcParser
{
    /**
     * @return array{jsonrpc:string,id:mixed,method:string,params:array<string,mixed>,notification:bool}
     */
    public function parse(string $payload): array
    {
        try {
            $request = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new JsonRpcException('Parse error', -32700);
        }

        if (!is_array($request) || array_is_list($request)) {
            throw new JsonRpcException('Invalid Request', -32600);
        }

        $id = $request['id'] ?? null;
        if (($request['jsonrpc'] ?? null) !== '2.0'
            || !isset($request['method'])
            || !is_string($request['method'])
            || $request['method'] === ''
        ) {
            throw new JsonRpcException('Invalid Request', -32600, $id);
        }

        $params = $request['params'] ?? [];
        if (!is_array($params) || ($params !== [] && array_is_list($params))) {
            throw new JsonRpcException('Invalid params', -32602, $id);
        }

        if (array_key_exists('id', $request)
            && !is_int($request['id'])
            && !is_string($request['id'])
            && $request['id'] !== null
        ) {
            throw new JsonRpcException('Invalid Request', -32600);
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $request['method'],
            'params' => $params,
            'notification' => !array_key_exists('id', $request),
        ];
    }
}
