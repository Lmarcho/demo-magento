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
}
