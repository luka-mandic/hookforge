<?php

namespace App\Services;

final class DeliveryResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBody = null,
        public readonly ?string $errorMessage = null,
        public readonly int $durationMs = 0,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public static function success(int $statusCode, ?string $responseBody, int $durationMs): self
    {
        return new self(
            successful: true,
            statusCode: $statusCode,
            responseBody: $responseBody,
            durationMs: $durationMs,
        );
    }

    public static function failed(?string $errorMessage, ?int $statusCode = null, ?string $responseBody = null, int $durationMs = 0): self
    {
        return new self(
            successful: false,
            statusCode: $statusCode,
            responseBody: $responseBody,
            errorMessage: $errorMessage,
            durationMs: $durationMs,
        );
    }
}
