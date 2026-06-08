<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model;

use Lmarcho\CommerceMcp\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testAllowedStoreCodesAreNormalizedAndAdminIsRemoved(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('commerce_mcp/general/allowed_store_codes')
            ->willReturn(' default, uk_store, admin, default, invalid-code ');

        self::assertSame(
            ['default', 'uk_store'],
            (new Config($scopeConfig))->getAllowedStoreCodes()
        );
    }

    public function testVariantPoliciesUseConfiguredValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('commerce_mcp/general/max_variants_per_product')
            ->willReturn(12);
        $scopeConfig->method('isSetFlag')
            ->with('commerce_mcp/general/variant_image_fallback_enabled')
            ->willReturn(true);
        $config = new Config($scopeConfig);

        self::assertSame(12, $config->getMaxVariantsPerProduct());
        self::assertTrue($config->isVariantImageFallbackEnabled());
    }

    public function testSearchAndRelatedLimitsUseConfiguredValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path): int => match ($path) {
                'commerce_mcp/general/max_search_results' => 8,
                'commerce_mcp/general/max_related_products' => 4,
                'commerce_mcp/general/max_promotions' => 9,
                default => 0,
            }
        );
        $config = new Config($scopeConfig);

        self::assertSame(8, $config->getMaxSearchResults());
        self::assertSame(4, $config->getMaxRelatedProducts());
        self::assertSame(9, $config->getMaxPromotions());
    }

    public function testPublicCouponCodesAreNormalized(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('commerce_mcp/general/public_coupon_codes')
            ->willReturn(' summer10, SUMMER10, welcome ');

        self::assertSame(
            ['SUMMER10', 'WELCOME'],
            (new Config($scopeConfig))->getPublicCouponCodes()
        );
    }

    public function testCustomerAssertionLifetimeIsClamped(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('commerce_mcp/general/customer_assertion_lifetime_seconds')
            ->willReturn(999);

        self::assertSame(300, (new Config($scopeConfig))->getCustomerAssertionLifetimeSeconds());
    }
}
