<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Product;

use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Product\MediaResolver;
use Lmarcho\CommerceMcp\Model\Product\VariantImageResolver;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class VariantImageResolverTest extends TestCase
{
    public function testUsesChildImageWithoutFallback(): void
    {
        $child = $this->createMock(Product::class);
        $parent = $this->createMock(Product::class);
        $media = $this->createMock(MediaResolver::class);
        $media->expects(self::once())->method('resolve')->with($child, 1)
            ->willReturn(['primary_image' => ['url' => 'child.jpg'], 'gallery' => []]);

        $result = (new VariantImageResolver(
            $this->createMock(Config::class),
            $media
        ))->resolve($child, $parent);

        self::assertSame('child.jpg', $result['primary_image']['url']);
        self::assertFalse($result['image_fallback']);
    }

    public function testFallsBackToParentWhenEnabled(): void
    {
        $child = $this->createMock(Product::class);
        $parent = $this->createMock(Product::class);
        $config = $this->createMock(Config::class);
        $config->method('isVariantImageFallbackEnabled')->willReturn(true);
        $media = $this->createMock(MediaResolver::class);
        $media->method('resolve')->willReturnMap([
            [$child, 1, ['primary_image' => null, 'gallery' => []]],
            [$parent, 1, ['primary_image' => ['url' => 'parent.jpg'], 'gallery' => []]],
        ]);

        $result = (new VariantImageResolver($config, $media))->resolve($child, $parent);

        self::assertSame('parent.jpg', $result['primary_image']['url']);
        self::assertTrue($result['image_fallback']);
    }

    public function testDoesNotFallbackWhenDisabled(): void
    {
        $child = $this->createMock(Product::class);
        $parent = $this->createMock(Product::class);
        $config = $this->createMock(Config::class);
        $config->method('isVariantImageFallbackEnabled')->willReturn(false);
        $media = $this->createMock(MediaResolver::class);
        $media->expects(self::once())->method('resolve')->with($child, 1)
            ->willReturn(['primary_image' => null, 'gallery' => []]);

        $result = (new VariantImageResolver($config, $media))->resolve($child, $parent);

        self::assertNull($result['primary_image']);
        self::assertFalse($result['image_fallback']);
    }
}
