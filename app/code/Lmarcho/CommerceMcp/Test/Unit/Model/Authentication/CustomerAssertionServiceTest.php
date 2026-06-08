<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Test\Unit\Model\Authentication;

use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Authentication\CustomerAssertionService;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\TestCase;

class CustomerAssertionServiceTest extends TestCase
{
    public function testIssuedAssertionVerifiesForSameStoreAndWebsite(): void
    {
        $service = $this->service();

        $claims = $service->verify($service->issue(123, 1, 1), 1, 1);

        self::assertSame(123, $claims['customer_id']);
        self::assertSame(1, $claims['store_id']);
        self::assertSame(1, $claims['website_id']);
        self::assertSame('nonce-12345678901234567890123456', $claims['nonce']);
    }

    public function testRejectsWrongStore(): void
    {
        $service = $this->service();

        try {
            $service->verify($service->issue(123, 1, 1), 2, 1);
            self::fail('Expected invalid assertion.');
        } catch (JsonRpcException $exception) {
            self::assertSame(
                'INVALID_CUSTOMER_ASSERTION',
                $exception->getErrorData()['error_code']
            );
        }
    }

    private function service(): CustomerAssertionService
    {
        $config = $this->createMock(Config::class);
        $config->method('getCustomerAssertionLifetimeSeconds')->willReturn(180);
        $config->method('getCustomerAssertionSigningKey')->willReturn('');
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->with('crypt/key')->willReturn('test-signing-key');
        $encryptor = $this->createMock(EncryptorInterface::class);
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->with(32)->willReturn('nonce-12345678901234567890123456');

        return new CustomerAssertionService($config, $deploymentConfig, $encryptor, $random);
    }
}
