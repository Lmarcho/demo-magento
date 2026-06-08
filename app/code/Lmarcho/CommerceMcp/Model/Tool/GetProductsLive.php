<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\ProductHydratorInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetProductsLive implements ToolInterface
{
    public function __construct(private readonly ProductHydratorInterface $productHydrator)
    {
    }

    public function getName(): string
    {
        return 'get_products_live';
    }

    public function getDescription(): string
    {
        return 'Return normalized live commerce data for requested SKUs.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'skus' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                ],
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['core', 'url', 'media', 'price', 'availability'],
                    ],
                    'uniqueItems' => true,
                ],
                'gallery_limit' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['store_code', 'skus'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(
            array_keys($arguments),
            ['store_code', 'skus', 'sections', 'gallery_limit']
        ) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        if (!isset($arguments['skus']) || !is_array($arguments['skus'])) {
            throw $this->invalidArguments('SKUS_REQUIRED');
        }
        $sections = $arguments['sections'] ?? [];
        if (!is_array($sections)) {
            throw $this->invalidArguments('INVALID_SECTIONS');
        }
        $galleryLimit = $arguments['gallery_limit'] ?? null;
        if ($galleryLimit !== null && (!is_int($galleryLimit) || $galleryLimit < 1)) {
            throw $this->invalidArguments('INVALID_GALLERY_LIMIT');
        }

        $result = $this->productHydrator->hydrate(
            $arguments['store_code'],
            $arguments['skus'],
            $sections,
            $galleryLimit
        );
        $structuredContent = [
            'schema_version' => '1.0',
            'products' => $result['products'],
            'errors' => $result['errors'],
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
