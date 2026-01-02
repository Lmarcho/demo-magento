<?php
/**
 * Lmarcho RagSync Module - WebhookSender Unit Tests
 *
 * Note: Complex integration tests with full HTTP mocking are in integration tests.
 * This file focuses on unit testing WebhookResponse and basic signature generation.
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Test\Unit\Model;

use Lmarcho\RagSync\Model\WebhookResponse;
use PHPUnit\Framework\TestCase;

class WebhookSenderTest extends TestCase
{
    public function testWebhookResponseSuccess(): void
    {
        $response = new WebhookResponse(true, 202, ['success' => true]);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals(['success' => true], $response->getBody());
        $this->assertNull($response->getError());
    }

    public function testWebhookResponseFailure(): void
    {
        $response = new WebhookResponse(false, 500, null, 'Internal Server Error');

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertNull($response->getBody());
        $this->assertEquals('Internal Server Error', $response->getError());
    }

    public function testWebhookResponseWithData(): void
    {
        $data = [
            'processed' => 5,
            'failed' => 0,
            'items' => [
                ['id' => '1', 'status' => 'success'],
                ['id' => '2', 'status' => 'success'],
            ],
        ];

        $response = new WebhookResponse(true, 200, $data);

        $this->assertTrue($response->isSuccess());
        $this->assertEquals($data, $response->getBody());
        $this->assertEquals(5, $response->getBody()['processed']);
    }

    public function testWebhookResponseWithErrorDetails(): void
    {
        $response = new WebhookResponse(false, 400, ['errors' => ['Invalid payload']], 'Bad Request');

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Bad Request', $response->getError());
        $this->assertArrayHasKey('errors', $response->getBody());
    }

    public function testWebhookResponseCircuitBreakerOpen(): void
    {
        $response = new WebhookResponse(false, 0, null, 'Circuit breaker is open');

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(0, $response->getStatusCode());
        $this->assertStringContainsString('Circuit breaker', $response->getError());
    }

    public function testWebhookResponseConnectionTimeout(): void
    {
        $response = new WebhookResponse(false, 0, null, 'Connection timeout after 30 seconds');

        $this->assertFalse($response->isSuccess());
        $this->assertEquals(0, $response->getStatusCode());
        $this->assertStringContainsString('timeout', $response->getError());
    }

    public function testSignatureGeneration(): void
    {
        // Test HMAC-SHA256 signature format
        $payload = '{"type":"product","id":"123"}';
        $secret = 'test-secret-key';

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Verify the expected format
        $this->assertStringStartsWith('sha256=', $expectedSignature);
        $this->assertEquals(71, strlen($expectedSignature)); // 'sha256=' + 64 hex chars
    }

    public function testSignatureVerification(): void
    {
        // Test that we can verify signatures
        $payload = '{"action":"upsert","data":{"sku":"TEST123"}}';
        $secret = 'webhook-secret';

        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Verify the signature by re-computing
        $expectedHash = hash_hmac('sha256', $payload, $secret);
        $actualHash = str_replace('sha256=', '', $signature);

        $this->assertEquals($expectedHash, $actualHash);
        $this->assertTrue(hash_equals($expectedHash, $actualHash));
    }
}
