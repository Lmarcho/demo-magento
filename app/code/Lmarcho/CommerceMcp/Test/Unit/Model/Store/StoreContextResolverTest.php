<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Store;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Store\StoreContextResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class StoreContextResolverTest extends TestCase
{
    public function testRejectsStoreOutsideAllowListBeforeLookup(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getAllowedStoreCodes')->willReturn(['default']);
        $repository = $this->createMock(StoreRepositoryInterface::class);
        $repository->expects(self::never())->method('getActiveStoreByCode');

        $resolver = new StoreContextResolver(
            $config,
            $repository,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(StockResolverInterface::class)
        );

        try {
            $resolver->resolve('other');
            self::fail('Expected store rejection.');
        } catch (JsonRpcException $exception) {
            self::assertSame(-32602, $exception->getRpcCode());
            self::assertSame('STORE_NOT_ALLOWED', $exception->getErrorData()['error_code']);
        }
    }

    public function testUnknownStoreUsesGenericPublicError(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getAllowedStoreCodes')->willReturn(['default']);
        $repository = $this->createMock(StoreRepositoryInterface::class);
        $repository->method('getActiveStoreByCode')
            ->willThrowException(new NoSuchEntityException());

        $resolver = new StoreContextResolver(
            $config,
            $repository,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(StockResolverInterface::class)
        );

        try {
            $resolver->resolve('default');
            self::fail('Expected store rejection.');
        } catch (JsonRpcException $exception) {
            self::assertSame('Store is not available', $exception->getMessage());
            self::assertSame('STORE_NOT_AVAILABLE', $exception->getErrorData()['error_code']);
        }
    }

    public function testResolvesStoreAndMsiSalesChannel(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getAllowedStoreCodes')->willReturn(['default']);

        $activeStore = $this->createMock(StoreInterface::class);
        $activeStore->method('getId')->willReturn(1);
        $activeStore->method('getWebsiteId')->willReturn(1);
        $repository = $this->createMock(StoreRepositoryInterface::class);
        $repository->method('getActiveStoreByCode')->with('default')->willReturn($activeStore);

        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getCode',
                'getName',
                'getCurrentCurrencyCode',
                'getBaseUrl',
            ])
            ->getMock();
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('getName')->willReturn('Default Store View');
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $store->method('getBaseUrl')->willReturnMap([
            ['web', true, 'https://store.example/'],
            ['media', true, 'https://cdn.example/media/'],
        ]);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $website->method('getCode')->willReturn('base');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);
        $storeManager->method('getWebsite')->with(1)->willReturn($website);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['general/locale/code', 'stores', 'default', 'en_US'],
            ['general/locale/timezone', 'stores', 'default', 'America/Los_Angeles'],
        ]);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(4);
        $stockResolver = $this->createMock(StockResolverInterface::class);
        $stockResolver->expects(self::once())
            ->method('execute')
            ->with('website', 'base')
            ->willReturn($stock);

        $context = (new StoreContextResolver(
            $config,
            $repository,
            $storeManager,
            $scopeConfig,
            $stockResolver
        ))->resolve('default');

        self::assertSame(4, $context->getStockId());
        self::assertSame('https://cdn.example/media/', $context->getSecureMediaBaseUrl());
        self::assertSame('base', $context->getSalesChannelCode());
    }

    public function testInventoryResolutionFailureIsExplicit(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getAllowedStoreCodes')->willReturn(['default']);
        $activeStore = $this->createMock(StoreInterface::class);
        $activeStore->method('getId')->willReturn(1);
        $activeStore->method('getWebsiteId')->willReturn(1);
        $repository = $this->createMock(StoreRepositoryInterface::class);
        $repository->method('getActiveStoreByCode')->willReturn($activeStore);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn(
            $this->getMockBuilder(Store::class)->disableOriginalConstructor()->getMock()
        );
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $storeManager->method('getWebsite')->willReturn($website);

        $stockResolver = $this->createMock(StockResolverInterface::class);
        $stockResolver->method('execute')->willThrowException(new \RuntimeException('MSI failed'));

        try {
            (new StoreContextResolver(
                $config,
                $repository,
                $storeManager,
                $this->createMock(ScopeConfigInterface::class),
                $stockResolver
            ))->resolve('default');
            self::fail('Expected stock context error.');
        } catch (JsonRpcException $exception) {
            self::assertSame(-32010, $exception->getRpcCode());
            self::assertSame(
                'STOCK_CONTEXT_UNAVAILABLE',
                $exception->getErrorData()['error_code']
            );
        }
    }
}
