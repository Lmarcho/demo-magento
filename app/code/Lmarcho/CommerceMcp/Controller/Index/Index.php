<?php

declare(strict_types=1);

namespace Lmarcho\CommerceMcp\Controller\Index;

use Lmarcho\CommerceMcp\Api\AuthenticationServiceInterface;
use Lmarcho\CommerceMcp\Model\Config;
use Lmarcho\CommerceMcp\Model\Mcp\ResponseBuilder;
use Lmarcho\CommerceMcp\Model\Mcp\Server;
use Lmarcho\CommerceMcp\Service\CorrelationId;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Psr\Log\LoggerInterface;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly RawFactory $rawFactory,
        private readonly Config $config,
        private readonly AuthenticationServiceInterface $authenticationService,
        private readonly CorrelationId $correlationId,
        private readonly ResponseBuilder $responseBuilder,
        private readonly Server $server,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $correlationId = $this->correlationId->resolve(
            $this->stringHeader('X-Correlation-ID')
        );

        if (!$this->config->isEnabled()) {
            return $this->jsonResponse(
                $this->responseBuilder->error(null, -32000, 'MCP server disabled', $correlationId),
                503,
                $correlationId
            );
        }

        $payload = $this->request->getContent();
        if (strlen($payload) > $this->config->getMaxRequestBytes()) {
            return $this->jsonResponse(
                $this->responseBuilder->error(null, -32005, 'Request too large', $correlationId),
                413,
                $correlationId
            );
        }

        $token = $this->extractBearerToken($this->stringHeader('Authorization'));
        $client = $token === null ? null : $this->authenticationService->authenticate($token);
        if ($client === null) {
            $this->logger->warning('Commerce MCP authentication failed', [
                'correlation_id' => $correlationId,
            ]);
            return $this->jsonResponse(
                $this->responseBuilder->error(null, -32001, 'Authentication failed', $correlationId),
                401,
                $correlationId
            );
        }

        $response = $this->server->handle($payload, $correlationId, $client->getAllowedTools());
        if ($response === null) {
            return $this->rawFactory->create()
                ->setHttpResponseCode(202)
                ->setHeader('X-Correlation-ID', $correlationId)
                ->setContents('');
        }

        $encoded = json_encode($response, JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > $this->config->getMaxResponseBytes()) {
            $response = $this->responseBuilder->error(
                $response['id'] ?? null,
                -32006,
                'Response too large',
                $correlationId
            );
        }

        return $this->jsonResponse($response, 200, $correlationId);
    }

    private function jsonResponse(array $data, int $status, string $correlationId)
    {
        return $this->jsonFactory->create()
            ->setHttpResponseCode($status)
            ->setHeader('X-Correlation-ID', $correlationId)
            ->setData($data);
    }

    private function stringHeader(string $name): ?string
    {
        $value = $this->request->getHeader($name);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function extractBearerToken(?string $header): ?string
    {
        if ($header === null || preg_match('/\ABearer ([^\s]+)\z/i', $header, $matches) !== 1) {
            return null;
        }
        return $matches[1];
    }
}
