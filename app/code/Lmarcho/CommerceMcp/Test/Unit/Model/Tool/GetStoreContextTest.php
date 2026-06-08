<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Tool;

use Lmarcho\CommerceMcp\Api\StoreContextResolverInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Store\StoreContext;
use Lmarcho\CommerceMcp\Model\Tool\GetStoreContext;
use PHPUnit\Framework\TestCase;

class GetStoreContextTest extends TestCase
{
    public function testSchemaRequiresStoreCode(): void
    {
        $tool = new GetStoreContext($this->createMock(StoreContextResolverInterface::class));

        self::assertSame(['store_code'], $tool->getInputSchema()['required']);
        self::assertFalse($tool->getInputSchema()['additionalProperties']);
    }

    public function testRejectsMissingStoreCode(): void
    {
        $tool = new GetStoreContext($this->createMock(StoreContextResolverInterface::class));

        try {
            $tool->execute([]);
            self::fail('Expected argument validation error.');
        } catch (JsonRpcException $exception) {
            self::assertSame('STORE_CODE_REQUIRED', $exception->getErrorData()['error_code']);
        }
    }

    public function testReturnsStructuredContent(): void
    {
        $context = new StoreContext(
            1,
            'default',
            'Default Store View',
            1,
            'base',
            'USD',
            'en_US',
            'UTC',
            'https://store.example/',
            'https://store.example/media/',
            'website',
            'base',
            1
        );
        $resolver = $this->createMock(StoreContextResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with('default')->willReturn($context);

        $result = (new GetStoreContext($resolver))->execute(['store_code' => 'default']);

        self::assertFalse($result['isError']);
        self::assertSame('1.0', $result['structuredContent']['schema_version']);
        self::assertSame('default', $result['structuredContent']['store']['store_code']);
        self::assertSame('text', $result['content'][0]['type']);
    }
}
