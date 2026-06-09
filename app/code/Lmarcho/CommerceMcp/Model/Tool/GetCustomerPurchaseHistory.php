<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Tool;

use Lmarcho\CommerceMcp\Api\CustomerPurchaseHistoryServiceInterface;
use Lmarcho\CommerceMcp\Api\ToolInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;

class GetCustomerPurchaseHistory implements ToolInterface
{
    public function __construct(private readonly CustomerPurchaseHistoryServiceInterface $purchaseHistoryService)
    {
    }

    public function getName(): string
    {
        return 'get_customer_purchase_history';
    }

    public function getDescription(): string
    {
        return 'Return bounded product-level purchase history for the asserted Magento customer.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'store_code' => ['type' => 'string', 'minLength' => 1],
                'customer_assertion' => ['type' => 'string', 'minLength' => 1],
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
            'required' => ['store_code', 'customer_assertion'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments): array
    {
        if (array_diff(
            array_keys($arguments),
            ['store_code', 'customer_assertion', 'sections', 'limit', 'gallery_limit', 'variant_limit']
        ) !== []) {
            throw $this->invalidArguments('UNKNOWN_ARGUMENT');
        }
        if (!isset($arguments['store_code']) || !is_string($arguments['store_code'])) {
            throw $this->invalidArguments('STORE_CODE_REQUIRED');
        }
        if (!isset($arguments['customer_assertion']) || !is_string($arguments['customer_assertion'])) {
            throw $this->invalidArguments('CUSTOMER_ASSERTION_REQUIRED');
        }
        $sections = $arguments['sections'] ?? [];
        if (!is_array($sections)) {
            throw $this->invalidArguments('INVALID_SECTIONS');
        }
        $limit = $this->optionalPositiveInt($arguments, 'limit', 'INVALID_PURCHASE_HISTORY_LIMIT');
        $galleryLimit = $this->optionalPositiveInt($arguments, 'gallery_limit', 'INVALID_GALLERY_LIMIT');
        $variantLimit = $this->optionalPositiveInt($arguments, 'variant_limit', 'INVALID_VARIANT_LIMIT');

        $data = $this->purchaseHistoryService->getHistory(
            $arguments['store_code'],
            $arguments['customer_assertion'],
            $sections,
            $limit,
            $galleryLimit,
            $variantLimit
        );

        return $this->result($data);
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

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function result(array $data): array
    {
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
            'Invalid tool arguments',
            -32602,
            null,
            ['error_code' => $errorCode]
        );
    }
}
