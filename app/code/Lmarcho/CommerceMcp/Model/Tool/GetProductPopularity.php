<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductPopularityServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetProductPopularity implements ToolInterface
{
    public function __construct(private readonly ProductPopularityServiceInterface $popularityService)
    {
    }

    public function getName(): string
    {
        return 'get_product_popularity';
    }

    public function getDescription(): string
    {
        return 'Return storefront-scoped aggregate product purchase counts for ranking.';
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
                'category_id' => ['type' => 'integer', 'minimum' => 1],
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128],
                'window_days' => ['type' => 'integer', 'minimum' => 0],
                'limit' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['store_code'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(
            array_keys($arguments),
            ['store_code', 'skus', 'category_id', 'query', 'window_days', 'limit']
        ) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        $skus = $arguments['skus'] ?? [];
        if (!is_array($skus)) {
            throw $this->invalidArguments('INVALID_SKUS');
        }
        $categoryId = $arguments['category_id'] ?? null;
        if ($categoryId !== null && (!is_int($categoryId) || $categoryId < 1)) {
            throw $this->invalidArguments('INVALID_CATEGORY_ID');
        }
        $query = $arguments['query'] ?? null;
        if ($query !== null && !is_string($query)) {
            throw $this->invalidArguments('INVALID_QUERY');
        }
        $windowDays = $arguments['window_days'] ?? 90;
        if (!is_int($windowDays) || $windowDays < 0) {
            throw $this->invalidArguments('INVALID_WINDOW_DAYS');
        }
        $limit = $arguments['limit'] ?? null;
        if ($limit !== null && (!is_int($limit) || $limit < 1)) {
            throw $this->invalidArguments('INVALID_LIMIT');
        }

        $data = $this->popularityService->get(
            $arguments['store_code'],
            $skus,
            $categoryId,
            $query,
            $windowDays,
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
                'text' => json_encode($structuredContent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]],
            'structuredContent' => $structuredContent,
            'isError' => false,
        ];
    }

    private function invalidArguments(string $errorCode): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid product popularity arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
