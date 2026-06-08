<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Product\MediaResolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class MediaResolverTest extends TestCase
{
    public function testNoSelectionReturnsNullPrimaryImage(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getImage', 'getMediaGalleryImages'])
            ->getMock();
        $product->method('getImage')->willReturn('no_selection');
        $product->method('getMediaGalleryImages')->willReturn([]);

        $result = (new MediaResolver(
            $this->createMock(MediaConfig::class),
            $this->createMock(StoreManagerInterface::class)
        ))->resolve($product, 5);

        self::assertNull($result['primary_image']);
        self::assertSame([], $result['gallery']);
    }

    public function testExcludesDisabledImagesAndMarksPrimary(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getImage', 'getName', 'getMediaGalleryImages'])
            ->getMock();
        $product->method('getImage')->willReturn('/primary.jpg');
        $product->method('getName')->willReturn('Product');
        $images = [new DataObject([
            'id' => 1,
            'file' => '/primary.jpg',
            'url' => 'https://cdn.example/primary.jpg',
            'label' => 'Front',
            'position' => 1,
            'disabled' => 0,
        ]), new DataObject([
            'id' => 2,
            'file' => '/disabled.jpg',
            'url' => 'https://cdn.example/disabled.jpg',
            'disabled' => 1,
        ])];
        $product->method('getMediaGalleryImages')->willReturn($images);
        $mediaConfig = $this->createMock(MediaConfig::class);
        $mediaConfig->method('getMediaShortUrl')->with('/primary.jpg')
            ->willReturn('catalog/product/primary.jpg');
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseUrl'])
            ->getMock();
        $store->method('getBaseUrl')->with('media', true)
            ->willReturn('https://cdn.example/media/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $result = (new MediaResolver($mediaConfig, $storeManager))->resolve($product, 5);

        self::assertCount(1, $result['gallery']);
        self::assertTrue($result['gallery'][0]['is_primary']);
        self::assertSame(
            'https://cdn.example/media/catalog/product/primary.jpg',
            $result['primary_image']['url']
        );
    }
}
