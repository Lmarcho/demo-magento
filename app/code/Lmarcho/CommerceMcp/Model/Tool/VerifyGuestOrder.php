<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\OrderStatusServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class VerifyGuestOrder implements ToolInterface
{
    public function __construct(private readonly OrderStatusServiceInterface $orderStatusService)
    {
    }

    public function getName(): string
    {
        return 'verify_guest_order';
    }

    public function getDescription(): string
    {
        return 'Verify a guest order using order number plus billing email or phone.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'order_number' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32],
                'contact' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 190],
            ],
            'required' => ['store_code', 'order_number', 'contact'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(array_keys($arguments), ['store_code', 'order_number', 'contact']) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        foreach (['store_code', 'order_number', 'contact'] as $key) {
            if (!isset($arguments[$key]) || !is_string($arguments[$key])) {
                throw $this->invalidArguments(strtoupper($key) . '_REQUIRED');
            }
        }

        $data = $this->orderStatusService->verifyGuest(
            $arguments['store_code'],
            $arguments['order_number'],
            $arguments['contact']
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
