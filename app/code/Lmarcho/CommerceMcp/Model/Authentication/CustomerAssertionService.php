<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Model\Authentication;

use Lmarcho\CommerceMcp\Api\CustomerAssertionServiceInterface;
use Lmarcho\CommerceMcp\Exception\JsonRpcException;
use Lmarcho\CommerceMcp\Model\Config;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;

class CustomerAssertionService implements CustomerAssertionServiceInterface
{
    private const ISSUER = 'Lmarcho_CommerceMcp';
    private const AUDIENCE = 'Lmarcho_CommerceMcp_OrderStatus';

    public function __construct(
        private readonly Config $config,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly Random $random
    ) {
    }

    public function issue(int $customerId, int $storeId, int $websiteId): string
    {
        $now = time();
        $payload = [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'sub' => $customerId,
            'store_id' => $storeId,
            'website_id' => $websiteId,
            'iat' => $now,
            'exp' => $now + $this->config->getCustomerAssertionLifetimeSeconds(),
            'nonce' => $this->random->getRandomString(32),
        ];
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $encodedPayload = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey(), true);

        return $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    public function verify(string $assertion, int $storeId, int $websiteId): array
    {
        $parts = explode('.', $assertion);
        if (count($parts) !== 2) {
            throw $this->invalidAssertion();
        }
        [$encodedPayload, $encodedSignature] = $parts;
        $expected = hash_hmac('sha256', $encodedPayload, $this->signingKey(), true);
        $actual = $this->base64UrlDecode($encodedSignature);
        if ($actual === null || !hash_equals($expected, $actual)) {
            throw $this->invalidAssertion();
        }
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === null) {
            throw $this->invalidAssertion();
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw $this->invalidAssertion();
        }

        $now = time();
        if (($payload['iss'] ?? null) !== self::ISSUER
            || ($payload['aud'] ?? null) !== self::AUDIENCE
            || !isset($payload['sub'], $payload['store_id'], $payload['website_id'], $payload['exp'], $payload['nonce'])
            || (int)$payload['store_id'] !== $storeId
            || (int)$payload['website_id'] !== $websiteId
            || (int)$payload['exp'] < $now
        ) {
            throw $this->invalidAssertion();
        }

        return [
            'customer_id' => (int)$payload['sub'],
            'store_id' => (int)$payload['store_id'],
            'website_id' => (int)$payload['website_id'],
            'nonce' => (string)$payload['nonce'],
            'exp' => (int)$payload['exp'],
        ];
    }

    private function signingKey(): string
    {
        $configured = $this->config->getCustomerAssertionSigningKey();
        if ($configured !== '') {
            return $this->encryptor->decrypt($configured) ?: $configured;
        }

        return (string)$this->deploymentConfig->get('crypt/key');
    }

    private function invalidAssertion(): JsonRpcException
    {
        return new JsonRpcException(
            'Invalid customer assertion',
            -32602,
            null,
            ['error_code' => 'INVALID_CUSTOMER_ASSERTION']
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
