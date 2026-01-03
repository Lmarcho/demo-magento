<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lmarcho\RagSync\Http\ClientFactory;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Lmarcho\RagSync\Model\Config;
use Lmarcho\RagSync\Model\CircuitBreaker;

class WebhookSender
{
    private const DEFAULT_TIMEOUT = 30;
    private const USER_AGENT = 'Magento-RagSync/1.0';

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CircuitBreaker
     */
    private CircuitBreaker $circuitBreaker;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ClientFactory $clientFactory
     * @param Config $config
     * @param CircuitBreaker $circuitBreaker
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientFactory $clientFactory,
        Config $config,
        CircuitBreaker $circuitBreaker,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->circuitBreaker = $circuitBreaker;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Send batch of items to webhook
     *
     * @param array $items Array of items to send
     * @param int|null $storeId
     * @return WebhookResponse
     */
    public function sendBatch(array $items, ?int $storeId = null): WebhookResponse
    {
        $endpoint = 'batch';
        $payload = [
            'type' => 'batch',
            'batch_id' => $this->generateBatchId(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'items' => $items,
        ];

        return $this->send($endpoint, $payload, $storeId);
    }

    /**
     * Send single entity to webhook
     *
     * @param string $entityType
     * @param string $action
     * @param array $data
     * @param int|null $storeId
     * @return WebhookResponse
     */
    public function sendEntity(string $entityType, string $action, array $data, ?int $storeId = null): WebhookResponse
    {
        $endpoint = $entityType;
        if ($action === 'delete') {
            $endpoint .= '/delete';
        }

        $payload = [
            'type' => $entityType,
            'action' => $action,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'data' => $data,
        ];

        return $this->send($endpoint, $payload, $storeId);
    }

    /**
     * Test connection to webhook endpoint using saved config
     *
     * @param int|null $storeId
     * @return WebhookResponse
     */
    public function testConnection(?int $storeId = null): WebhookResponse
    {
        $url = $this->config->getWebhookEndpoint('', $storeId);
        $secret = $this->config->getApiSecret($storeId);

        return $this->testConnectionWithCredentials($url, $secret);
    }

    /**
     * Test connection with provided credentials (for testing before save)
     *
     * @param string $webhookUrl
     * @param string $apiSecret
     * @return WebhookResponse
     */
    public function testConnectionWithCredentials(string $webhookUrl, string $apiSecret): WebhookResponse
    {
        try {
            $client = $this->createClient();

            // Create test payload
            $payload = [
                'type' => 'connection_test',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'test_id' => bin2hex(random_bytes(8)),
            ];

            $jsonPayload = $this->serializer->serialize($payload);
            $signature = hash_hmac('sha256', $jsonPayload, $apiSecret);

            $headers = [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Magento-Webhook-Signature' => 'sha256=' . $signature,
                'X-Environment' => $this->config->getEnvironment(),
            ];

            $startTime = microtime(true);
            $response = $client->post($webhookUrl, [
                'headers' => $headers,
                'body' => $jsonPayload,
            ]);
            $duration = (int)((microtime(true) - $startTime) * 1000);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            return new WebhookResponse(
                $success,
                $statusCode,
                $this->parseResponseBody($response),
                null,
                $duration
            );
        } catch (GuzzleException $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Send request to webhook
     *
     * @param string $endpoint
     * @param array $payload
     * @param int|null $storeId
     * @return WebhookResponse
     */
    private function send(string $endpoint, array $payload, ?int $storeId = null): WebhookResponse
    {
        // Check circuit breaker
        if (!$this->circuitBreaker->isAvailable()) {
            $this->logger->warning('RagSync: Circuit breaker is open, skipping request');
            return new WebhookResponse(
                false,
                503,
                null,
                'Circuit breaker is open - service temporarily unavailable',
                0
            );
        }

        try {
            $client = $this->createClient($storeId);
            $url = $this->config->getWebhookEndpoint($endpoint, $storeId);
            $jsonPayload = $this->serializer->serialize($payload);
            $signature = $this->generateSignature($jsonPayload, $storeId);

            $headers = $this->getHeaders($signature, $storeId);
            $headers['Content-Type'] = 'application/json';

            if ($this->config->isDebugEnabled()) {
                $this->logger->debug('RagSync: Sending webhook', [
                    'url' => $url,
                    'endpoint' => $endpoint,
                    'payload_size' => strlen($jsonPayload),
                ]);
            }

            $startTime = microtime(true);
            $response = $client->post($url, [
                'headers' => $headers,
                'body' => $jsonPayload,
            ]);
            $duration = (int)((microtime(true) - $startTime) * 1000);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            if ($success) {
                $this->circuitBreaker->recordSuccess();
            }

            if ($this->config->isDebugEnabled()) {
                $this->logger->debug('RagSync: Webhook response', [
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                    'success' => $success,
                ]);
            }

            return new WebhookResponse(
                $success,
                $statusCode,
                $this->parseResponseBody($response),
                null,
                $duration
            );
        } catch (GuzzleException $e) {
            $this->circuitBreaker->recordFailure();
            return $this->handleException($e);
        }
    }

    /**
     * Create HTTP client
     *
     * @param int|null $storeId
     * @return Client
     */
    private function createClient(?int $storeId = null): Client
    {
        $timeout = $this->config->getConnectionTimeout();

        return $this->clientFactory->create([
            'config' => [
                'timeout' => $timeout,
                'connect_timeout' => min($timeout, 10),
                'http_errors' => false,
                'verify' => true,
            ],
        ]);
    }

    /**
     * Get request headers
     *
     * @param string|null $signature
     * @param int|null $storeId
     * @return array
     */
    private function getHeaders(?string $signature, ?int $storeId = null): array
    {
        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
            'X-Tenant-Id' => $this->config->getTenantId($storeId),
            'X-Environment' => $this->config->getEnvironment(),
        ];

        if ($signature !== null) {
            $headers['X-Magento-Webhook-Signature'] = 'sha256=' . $signature;
        }

        return $headers;
    }

    /**
     * Generate HMAC-SHA256 signature
     *
     * @param string $payload
     * @param int|null $storeId
     * @return string
     */
    private function generateSignature(string $payload, ?int $storeId = null): string
    {
        $secret = $this->config->getApiSecret($storeId);
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Generate unique batch ID
     *
     * @return string
     */
    private function generateBatchId(): string
    {
        return sprintf(
            '%s-%s-%s',
            'mag',
            date('YmdHis'),
            bin2hex(random_bytes(4))
        );
    }

    /**
     * Parse response body
     *
     * @param Response $response
     * @return array|null
     */
    private function parseResponseBody(Response $response): ?array
    {
        $body = (string)$response->getBody();

        if (empty($body)) {
            return null;
        }

        try {
            return $this->serializer->unserialize($body);
        } catch (\Exception $e) {
            return ['raw' => $body];
        }
    }

    /**
     * Handle Guzzle exception
     *
     * @param GuzzleException $e
     * @return WebhookResponse
     */
    private function handleException(GuzzleException $e): WebhookResponse
    {
        $statusCode = 0;
        $message = $e->getMessage();
        $body = null;

        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $this->parseResponseBody($response);
        }

        $this->logger->error('RagSync: Webhook request failed', [
            'error' => $message,
            'status_code' => $statusCode,
        ]);

        return new WebhookResponse(
            false,
            $statusCode,
            $body,
            $message,
            0
        );
    }
}
