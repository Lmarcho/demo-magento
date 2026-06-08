<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\RelatedProductServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetRelatedProducts implements ToolInterface
{
    public function __construct(private readonly RelatedProductServiceInterface $relatedProductService)
    {
    }

    public function getName(): string
    {
        return 'get_related_products';
    }

    public function getDescription(): string
    {
        return 'Return related, upsell, and cross-sell products for a public storefront product.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'sku' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                'link_types' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['related', 'upsell', 'crosssell'],
                    ],
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
            'required' => ['store_code', 'sku'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(
            array_keys($arguments),
            ['store_code', 'sku', 'link_types', 'sections', 'limit', 'gallery_limit', 'variant_limit']
        ) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        if (!isset($arguments['sku']) || !is_string($arguments['sku'])) {
            throw $this->invalidArguments('SKU_REQUIRED');
        }
        $linkTypes = $arguments['link_types'] ?? [];
        if (!is_array($linkTypes)) {
            throw $this->invalidArguments('INVALID_LINK_TYPES');
        }
        $sections = $arguments['sections'] ?? [];
        if (!is_array($sections)) {
            throw $this->invalidArguments('INVALID_SECTIONS');
        }
        $limit = $this->optionalPositiveInt($arguments, 'limit', 'INVALID_RELATED_LIMIT');
        $galleryLimit = $this->optionalPositiveInt($arguments, 'gallery_limit', 'INVALID_GALLERY_LIMIT');
        $variantLimit = $this->optionalPositiveInt($arguments, 'variant_limit', 'INVALID_VARIANT_LIMIT');

        $data = $this->relatedProductService->get(
            $arguments['store_code'],
            $arguments['sku'],
            $linkTypes,
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
