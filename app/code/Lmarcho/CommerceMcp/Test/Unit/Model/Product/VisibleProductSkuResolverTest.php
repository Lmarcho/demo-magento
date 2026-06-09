<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Product\VisibleProductSkuResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VisibleProductSkuResolverTest extends TestCase
{
    public function testReturnsVisibleSkuWithoutParentLookup(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::once())->method('create')
            ->willReturn($this->collectionReturning($this->product(10, '24-MB01')));
        $configurable = $this->createMock(Configurable::class);
        $configurable->expects(self::never())->method('getParentIdsByChild');

        $resolver = new VisibleProductSkuResolver(
            $this->storeManager(),
            $factory,
            $configurable
        );

        self::assertSame('24-MB01', $resolver->resolve(' 24-MB01 ', 1));
    }

    public function testResolvesHiddenChildSkuToVisibleParentSku(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::exactly(3))->method('create')
            ->willReturnOnConsecutiveCalls(
                $this->collectionReturning($this->product(null, 'WS03-XS-Red')),
                $this->collectionReturning($this->product(22, 'WS03-XS-Red')),
                $this->collectionReturning($this->product(33, 'WS03'))
            );
        $configurable = $this->createMock(Configurable::class);
        $configurable->expects(self::once())->method('getParentIdsByChild')
            ->with(22)
            ->willReturn([33]);

        $resolver = new VisibleProductSkuResolver(
            $this->storeManager(),
            $factory,
            $configurable
        );

        self::assertSame('WS03', $resolver->resolve('WS03-XS-Red', 1));
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);

        return $storeManager;
    }

    private function product(?int $id, string $sku): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getSku'])
            ->getMock();
        $product->method('getId')->willReturn($id);
        $product->method('getSku')->willReturn($sku);

        return $product;
    }

    /**
     * @return Collection&MockObject
     */
    private function collectionReturning(Product $product): Collection
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'setStoreId',
                'addStoreFilter',
                'addAttributeToSelect',
                'addAttributeToFilter',
                'setOrder',
                'setPageSize',
                'getFirstItem',
            ])
            ->getMock();
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('addStoreFilter')->willReturnSelf();
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($product);

        return $collection;
    }
}
