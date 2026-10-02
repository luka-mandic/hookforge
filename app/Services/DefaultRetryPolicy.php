<?php

namespace App\Services;

final class DefaultRetryPolicy implements RetryPolicy
{
    public const DEFAULT_MAX_ATTEMPTS = 5;

    public const DEFAULT_INITIAL_DELAY_MS = 1000;

    public const DEFAULT_MAX_DELAY_MS = 30000;

    public int $maxAttempts;

    public int $initialDelayMs;

    public int $maxDelayMs;

    public int $multiplier;

    public function __construct(
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $initialDelayMs = self::DEFAULT_INITIAL_DELAY_MS,
        int $maxDelayMs = self::DEFAULT_MAX_DELAY_MS,
        int $multiplier = 2,
    ) {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->initialDelayMs = max(0, $initialDelayMs);
        $this->maxDelayMs = max($this->initialDelayMs, $maxDelayMs);
        $this->multiplier = max(2, $multiplier);
    }

    public function shouldRetry(DeliveryResult $result, int $attemptNumber): bool
    {
        if ($attemptNumber >= $this->maxAttempts) {
            return false;
        }

        if ($result->statusCode !== null && $this->isPermanentStatusCode($result->statusCode)) {
            return false;
        }

        if ($result->statusCode === null && $this->isTimeoutError($result->errorMessage)) {
            return true;
        }

        if ($result->statusCode === null) {
            return true;
        }

        return in_array($result->statusCode, [429, 500, 502, 503, 504], true);
    }

    public function delayForAttempt(int $attemptNumber): int
    {
        if ($attemptNumber <= 1) {
            return $this->initialDelayMs;
        }

        $delay = $this->initialDelayMs * ($this->multiplier ** ($attemptNumber - 1));

        return min($delay, $this->maxDelayMs);
    }

    protected function isPermanentStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, [400, 401, 403, 404, 405, 410, 422], true);
    }

    protected function isTimeoutError(?string $errorMessage): bool
    {
        if ($errorMessage === null) {
            return false;
        }

        $message = strtolower($errorMessage);

        return str_contains($message, 'timeout') || str_contains($message, 'timed out');
    }
}
