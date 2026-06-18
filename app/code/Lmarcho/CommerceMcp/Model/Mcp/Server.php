<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Mcp;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Service\RateLimiter;
use Psr\Log\LoggerInterface;

class Server
{
    public function __construct(
        private readonly JsonRpcParser $parser,
        private readonly ProtocolNegotiator $protocolNegotiator,
        private readonly ResponseBuilder $responseBuilder,
        private readonly ToolRegistry $toolRegistry,
        private readonly Config $config,
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(
        string $payload,
        string $correlationId,
        array $allowedTools,
        ?int $clientId = null
    ): ?array {
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
                'tools/call' => $this->callTool($request, $allowedTools, $correlationId, $clientId),
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
                'version' => '0.9.0',
            ],
            'instructions' => 'Read-only commerce tools. Customer cart, purchase history, and order status require customer proof.',
        ];
    }

    private function callTool(
        array $request,
        array $allowedTools,
        string $correlationId,
        ?int $clientId
    ): array {
        $name = $request['params']['name'] ?? null;
        if (!is_string($name) || !$this->toolRegistry->exists($name)) {
            throw new JsonRpcException('Unknown tool', -32602, $request['id']);
        }
        if (!in_array($name, $allowedTools, true)) {
            $this->logger->warning('Commerce MCP tool access denied', [
                'correlation_id' => $correlationId,
                'client_id' => $clientId,
                'tool' => $name,
            ]);
            throw new JsonRpcException('Access denied', -32003, $request['id']);
        }
        if (in_array($name, ['get_order_status', 'verify_guest_order'], true)
            && $clientId !== null
            && !$this->rateLimiter->isAllowed(
                'order_status:' . $clientId,
                $this->config->getOrderStatusRateLimitPerMinute()
            )
        ) {
            $this->logger->warning('Commerce MCP order status rate limited', [
                'correlation_id' => $correlationId,
                'client_id' => $clientId,
                'tool' => $name,
            ]);
            throw new JsonRpcException('Rate limit exceeded', -32007, $request['id']);
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

        $started = microtime(true);
        try {
            $result = $tool->execute($arguments);
        } finally {
            $this->logger->info('Commerce MCP tool call completed', [
                'correlation_id' => $correlationId,
                'client_id' => $clientId,
                'tool' => $name,
                'store_code' => $this->safeString($arguments['store_code'] ?? null),
                'sku_count' => $this->countList($arguments['skus'] ?? null),
                'has_customer_assertion' => isset($arguments['customer_assertion']),
                'has_guest_contact' => isset($arguments['contact']),
                'duration_ms' => round((microtime(true) - $started) * 1000, 2),
            ]);
        }

        if (($result['structuredContent']['errors'] ?? []) !== []) {
            $this->logger->info('Commerce MCP tool partial errors', [
                'correlation_id' => $correlationId,
                'client_id' => $clientId,
                'tool' => $name,
                'partial_error_count' => count($result['structuredContent']['errors']),
            ]);
        }

        return $result;
    }

    private function safeString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function countList(mixed $value): ?int
    {
        return is_array($value) && array_is_list($value) ? count($value) : null;
    }
}
