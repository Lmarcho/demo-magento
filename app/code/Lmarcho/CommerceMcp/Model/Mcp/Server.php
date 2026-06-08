<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Psr\Log\LoggerInterface;

class Server
{
    public function __construct(
        private readonly JsonRpcParser $parser,
        private readonly ProtocolNegotiator $protocolNegotiator,
        private readonly ResponseBuilder $responseBuilder,
        private readonly ToolRegistry $toolRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(string $payload, string $correlationId, array $allowedTools): ?array
    {
        $requestId = null;

        try {
            $request = $this->parser->parse($payload);
            $requestId = $request['id'];

            if ($request['notification']) {
                if ($request['method'] !== 'notifications/initialized') {
                    $this->logger->warning('Ignored unsupported MCP notification', [
                        'correlation_id' => $correlationId,
                        'method' => $request['method'],
                    ]);
                }
                return null;
            }

            $result = match ($request['method']) {
                'initialize' => $this->initialize($request),
                'ping' => [],
                'tools/list' => ['tools' => $this->toolRegistry->list($allowedTools)],
                'tools/call' => $this->callTool($request, $allowedTools),
                default => throw new JsonRpcException('Method not found', -32601, $requestId),
            };

            return $this->responseBuilder->success($requestId, $result, $correlationId);
        } catch (JsonRpcException $exception) {
            return $this->responseBuilder->error(
                $exception->getRequestId() ?? $requestId,
                $exception->getRpcCode(),
                $exception->getMessage(),
                $correlationId,
                $exception->getErrorData()
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Commerce MCP request failed', [
                'correlation_id' => $correlationId,
                'exception' => $exception,
            ]);

            return $this->responseBuilder->error(
                $requestId,
                -32603,
                'Internal error',
                $correlationId
            );
        }
    }

    private function initialize(array $request): array
    {
        $version = $request['params']['protocolVersion'] ?? null;

        return [
            'protocolVersion' => $this->protocolNegotiator->negotiate(
                is_string($version) ? $version : null,
                $request['id']
            ),
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'Lmarcho Commerce MCP',
                'version' => '0.4.0',
            ],
            'instructions' => 'Read-only public commerce tools. Order status requires customer proof.',
        ];
    }

    private function callTool(array $request, array $allowedTools): array
    {
        $name = $request['params']['name'] ?? null;
        if (!is_string($name) || !$this->toolRegistry->exists($name)) {
            throw new JsonRpcException('Unknown tool', -32602, $request['id']);
        }
        if (!in_array($name, $allowedTools, true)) {
            throw new JsonRpcException('Access denied', -32003, $request['id']);
        }

        $tool = $this->toolRegistry->getImplemented($name);
        if ($tool === null) {
            throw new JsonRpcException(
                'Tool implementation is not available in this phase',
                -32004,
                $request['id'],
                ['tool' => $name]
            );
        }

        $arguments = $request['params']['arguments'] ?? [];
        if (!is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            throw new JsonRpcException('Invalid tool arguments', -32602, $request['id']);
        }

        return $tool->execute($arguments);
    }
}
