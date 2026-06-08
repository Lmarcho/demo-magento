<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetStoreContext implements ToolInterface
{
    public function __construct(
        private readonly StoreContextResolverInterface $storeContextResolver
    ) {
    }

    public function getName(): string
    {
        return 'get_store_context';
    }

    public function getDescription(): string
    {
        return 'Return the resolved public Magento store context.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Magento storefront code.',
                ],
            ],
            'required' => ['store_code'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(array_keys($arguments), ['store_code']) !== []) {
            throw new JsonRpcException(
                'Invalid tool arguments',
                -32602,
                null,
                ['error_code' => 'UNKNOWN_ARGUMENT']
            );
        }

        $storeCode = $arguments['store_code'] ?? null;
        if (!is_string($storeCode) || $storeCode === '') {
            throw new JsonRpcException(
                'Invalid tool arguments',
                -32602,
                null,
                ['error_code' => 'STORE_CODE_REQUIRED']
            );
        }

        $structuredContent = [
            'schema_version' => '1.0',
            'store' => $this->storeContextResolver->resolve($storeCode)->toArray(),
            'fetched_at' => gmdate('c'),
        ];

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode(
                        $structuredContent,
                        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                ],
            ],
            'structuredContent' => $structuredContent,
            'isError' => false,
        ];
    }
}
