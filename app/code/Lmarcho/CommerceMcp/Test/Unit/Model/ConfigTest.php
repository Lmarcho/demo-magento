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
}
