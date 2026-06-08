<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\OrderStatusServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetOrderStatus implements ToolInterface
{
    public function __construct(private readonly OrderStatusServiceInterface $orderStatusService)
    {
    }

    public function getName(): string
    {
        return 'get_order_status';
    }

    public function getDescription(): string
    {
        return 'Return a customer-owned order status using a Magento customer assertion.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'order_number' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32],
                'customer_assertion' => ['type' => 'string', 'minLength' => 1],
            ],
            'required' => ['store_code', 'order_number', 'customer_assertion'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(array_keys($arguments), ['store_code', 'order_number', 'customer_assertion']) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        foreach (['store_code', 'order_number', 'customer_assertion'] as $key) {
            if (!isset($arguments[$key]) || !is_string($arguments[$key])) {
                throw $this->invalidArguments(strtoupper($key) . '_REQUIRED');
            }
        }

        $data = $this->orderStatusService->get(
            $arguments['store_code'],
            $arguments['order_number'],
            $arguments['customer_assertion']
        );
        $structuredContent = [
            'schema_version' => '1.0',
            ...$data,
            'fetched_at' => gmdate('c'),
        ];

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode(
                    $structuredContent,
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
            ]],
            'structuredContent' => $structuredContent,
            'isError' => false,
        ];
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid tool arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
