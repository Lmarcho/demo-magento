<?php
/**
 * Lmarcho RagSync Module - Config Unit Tests
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Unit\Model;

use Lmarcho\RagSync\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfigMock;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptorMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);

        $this->config = new Config(
            $this->scopeConfigMock,
            $this->encryptorMock
        );
    }

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('rag_sync/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('rag_sync/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    public function testGetWebhookUrl(): void
    {
        $expectedUrl = 'https://rag-backend.example.com';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/connection/webhook_url', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->config->getWebhookUrl());
    }

    public function testGetApiSecretDecryptsValue(): void
    {
        $encryptedSecret = 'encrypted_secret_value';
        $decryptedSecret = 'actual_secret_key';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/connection/api_secret', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($encryptedSecret);

        $this->encryptorMock->expects($this->once())
            ->method('decrypt')
            ->with($encryptedSecret)
            ->willReturn($decryptedSecret);

        $this->assertEquals($decryptedSecret, $this->config->getApiSecret());
    }

    public function testGetTenantId(): void
    {
        $expectedTenantId = 'tenant-123';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/connection/tenant_id', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expectedTenantId);

        $this->assertEquals($expectedTenantId, $this->config->getTenantId());
    }

    public function testGetQueueBatchSize(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/queue/batch_size', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('100');

        $this->assertEquals(100, $this->config->getQueueBatchSize());
    }

    public function testGetQueueBatchSizeReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/queue/batch_size', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(50, $this->config->getQueueBatchSize());
    }

    public function testGetMaxRetries(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/queue/max_retries', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('5');

        $this->assertEquals(5, $this->config->getMaxRetries());
    }

    public function testGetConnectionTimeout(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/connection/timeout', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('60');

        $this->assertEquals(60, $this->config->getConnectionTimeout());
    }

    public function testGetEnvironment(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/general/environment', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('staging');

        $this->assertEquals('staging', $this->config->getEnvironment());
    }

    public function testIsDebugEnabled(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('rag_sync/general/debug', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->isDebugEnabled());
    }

    public function testGetCmsPagesSyncMode(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/cms_pages/sync_mode', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('whitelist');

        $this->assertEquals('whitelist', $this->config->getCmsPagesSyncMode());
    }

    public function testGetCmsPagesIdentifiers(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/cms_pages/identifiers', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('privacy-policy,returns,faq');

        $expected = ['privacy-policy', 'returns', 'faq'];
        $this->assertEquals($expected, $this->config->getCmsPagesIdentifiers());
    }

    public function testGetCmsPagesIdentifiersReturnsEmptyArrayWhenNull(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/cms_pages/identifiers', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals([], $this->config->getCmsPagesIdentifiers());
    }

    public function testGetRetryDelays(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/queue/retry_delays', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('5,15,60');

        $expected = [5, 15, 60];
        $this->assertEquals($expected, $this->config->getRetryDelays());
    }

    public function testGetProductSyncAttributes(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('rag_sync/products/sync_attributes', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('brand,color,size');

        $expected = ['brand', 'color', 'size'];
        $this->assertEquals($expected, $this->config->getProductSyncAttributes());
    }
}
