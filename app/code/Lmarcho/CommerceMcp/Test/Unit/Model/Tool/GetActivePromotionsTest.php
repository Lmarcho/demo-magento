<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\PromotionServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Tool\GetActivePromotions;
use PHPUnit\Framework\TestCase;

class GetActivePromotionsTest extends TestCase
{
    public function testSchemaRequiresStoreOnly(): void
    {
        $tool = new GetActivePromotions(
            $this->createMock(PromotionServiceInterface::class)
        );

        self::assertSame(['store_code'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $tool = new GetActivePromotions(
            $this->createMock(PromotionServiceInterface::class)
        );

        try {
            $tool->execute(['store_code' => 'default', 'limit' => 0]);
            self::fail('Expected invalid promotion limit error.');
        } catch (JsonRpcException $exception) {
            self::assertSame(
                'INVALID_PROMOTION_LIMIT',
                $exception->getErrorData()['error_code']
            );
        }
    }

    public function testReturnsStructuredPromotionData(): void
    {
        $service = $this->createMock(PromotionServiceInterface::class);
        $service->expects(self::once())->method('getActive')
            ->with('default', ['24-MB01'], ['cart'], 2)
            ->willReturn([
                'store' => ['store_code' => 'default', 'timezone' => 'UTC'],
                'promotions' => [[
                    'external_id' => '1',
                    'type' => 'cart',
                    'name' => 'Bag Sale',
                    'coupon_required' => false,
                ]],
                'total' => 1,
                'returned' => 1,
            ]);

        $result = (new GetActivePromotions($service))->execute([
            'store_code' => 'default',
            'skus' => ['24-MB01'],
            'promotion_types' => ['cart'],
            'limit' => 2,
        ]);

        self::assertSame('1', $result['structuredContent']['promotions'][0]['external_id']);
        self::assertSame('cart', $result['structuredContent']['promotions'][0]['type']);
        self::assertFalse($result['isError']);
    }
}
