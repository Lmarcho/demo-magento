<?php
/**
 * Lmarcho RagSync Module
 */

declare(strict_types=1);

namespace Lmarcho\RagSync\Model;

class WebhookResponse
{
    /**
     * @var bool
     */
    private bool $success;

    /**
     * @var int
     */
    private int $statusCode;

    /**
     * @var array|null
     */
    private ?array $body;

    /**
     * @var string|null
     */
    private ?string $error;

    /**
     * @var int
     */
    private int $durationMs;

    /**
     * @param bool $success
     * @param int $statusCode
     * @param array|null $body
     * @param string|null $error
     * @param int $durationMs
     */
    public function __construct(
        bool $success,
        int $statusCode,
        ?array $body = null,
        ?string $error = null,
        int $durationMs = 0
    ) {
        $this->success = $success;
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->error = $error;
        $this->durationMs = $durationMs;
    }

    /**
     * Check if request was successful
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get response body
     *
     * @return array|null
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get request duration in milliseconds
     *
     * @return int
     */
    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * Check if error is retryable
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        // Retry on server errors and timeouts
        if ($this->statusCode >= 500 || $this->statusCode === 0) {
            return true;
        }

        // Retry on rate limiting
        if ($this->statusCode === 429) {
            return true;
        }

        return false;
    }

    /**
     * Check if error is permanent (should not retry)
     *
     * @return bool
     */
    public function isPermanentError(): bool
    {
        // Don't retry on client errors (except rate limiting)
        return $this->statusCode >= 400 && $this->statusCode < 500 && $this->statusCode !== 429;
    }

    /**
     * Get error message for logging/display
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        if ($this->success) {
            return '';
        }

        $message = $this->error ?? 'Unknown error';

        if ($this->statusCode > 0) {
            $message = sprintf('[HTTP %d] %s', $this->statusCode, $message);
        }

        return $message;
    }

    /**
     * Convert to array for logging
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'error' => $this->error,
            'duration_ms' => $this->durationMs,
            'retryable' => $this->isRetryable(),
        ];
    }
}
