<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductSearchServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class SearchProductsLive implements ToolInterface
{
    public function __construct(private readonly ProductSearchServiceInterface $searchService)
    {
    }

    public function getName(): string
    {
        return 'search_products_live';
    }

    public function getDescription(): string
    {
        return 'Search public storefront products and return normalized live commerce data.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 128],
                'candidate_skus' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                    'uniqueItems' => true,
                ],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['core', 'url', 'media', 'price', 'availability', 'variants'],
                    ],
                    'uniqueItems' => true,
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1],
                'gallery_limit' => ['type' => 'integer', 'minimum' => 1],
                'variant_limit' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['store_code'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(
            array_keys($arguments),
            ['store_code', 'query', 'candidate_skus', 'sections', 'limit', 'gallery_limit', 'variant_limit']
        ) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        $query = $arguments['query'] ?? null;
        if ($query !== null && !is_string($query)) {
            throw $this->invalidArguments('INVALID_SEARCH_QUERY');
        }
        $candidateSkus = $arguments['candidate_skus'] ?? [];
        if (!is_array($candidateSkus)) {
            throw $this->invalidArguments('INVALID_CANDIDATE_SKUS');
        }
        $sections = $arguments['sections'] ?? [];
        if (!is_array($sections)) {
            throw $this->invalidArguments('INVALID_SECTIONS');
        }
        $limit = $this->optionalPositiveInt($arguments, 'limit', 'INVALID_SEARCH_LIMIT');
        $galleryLimit = $this->optionalPositiveInt($arguments, 'gallery_limit', 'INVALID_GALLERY_LIMIT');
        $variantLimit = $this->optionalPositiveInt($arguments, 'variant_limit', 'INVALID_VARIANT_LIMIT');

        $data = $this->searchService->search(
            $arguments['store_code'],
            $query,
            $candidateSkus,
            $sections,
            $limit,
            $galleryLimit,
            $variantLimit
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

    /**
     * @param array<string,mixed> $arguments
     */
    private function optionalPositiveInt(array $arguments, string $key, string $errorCode): ?int
    {
        $value = $arguments[$key] ?? null;
        if ($value !== null && (!is_int($value) || $value < 1)) {
            throw $this->invalidArguments($errorCode);
        }

        return $value;
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
