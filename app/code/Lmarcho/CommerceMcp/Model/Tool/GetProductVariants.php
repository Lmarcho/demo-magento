<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductVariantServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetProductVariants implements ToolInterface
{
    public function __construct(private readonly ProductVariantServiceInterface $variantService)
    {
    }

    public function getName(): string
    {
        return 'get_product_variants';
    }

    public function getDescription(): string
    {
        return 'Return bounded configurable-product option and child variant data.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'sku' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                'limit' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['store_code', 'sku'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(array_keys($arguments), ['store_code', 'sku', 'limit']) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        if (!isset($arguments['sku']) || !is_string($arguments['sku'])) {
            throw $this->invalidArguments('SKU_REQUIRED');
        }
        $limit = $arguments['limit'] ?? null;
        if ($limit !== null && (!is_int($limit) || $limit < 1)) {
            throw $this->invalidArguments('INVALID_VARIANT_LIMIT');
        }

        $data = $this->variantService->get(
            $arguments['store_code'],
            $arguments['sku'],
            $limit
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
