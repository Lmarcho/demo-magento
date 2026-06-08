<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\PromotionServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetActivePromotions implements ToolInterface
{
    public function __construct(private readonly PromotionServiceInterface $promotionService)
    {
    }

    public function getName(): string
    {
        return 'get_active_promotions';
    }

    public function getDescription(): string
    {
        return 'Return public active promotion summaries for a storefront.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'skus' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                    'uniqueItems' => true,
                ],
                'promotion_types' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['catalog', 'cart'],
                    ],
                    'uniqueItems' => true,
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['store_code'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(array_keys($arguments), ['store_code', 'skus', 'promotion_types', 'limit']) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        $skus = $arguments['skus'] ?? [];
        if (!is_array($skus)) {
            throw $this->invalidArguments('INVALID_SKUS');
        }
        $promotionTypes = $arguments['promotion_types'] ?? [];
        if (!is_array($promotionTypes)) {
            throw $this->invalidArguments('INVALID_PROMOTION_TYPES');
        }
        $limit = $arguments['limit'] ?? null;
        if ($limit !== null && (!is_int($limit) || $limit < 1)) {
            throw $this->invalidArguments('INVALID_PROMOTION_LIMIT');
        }

        $data = $this->promotionService->getActive(
            $arguments['store_code'],
            $skus,
            $promotionTypes,
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
