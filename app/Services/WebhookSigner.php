<?php

namespace App\Services;

final class WebhookSigner
{
    /**
     * @param  array<string, mixed>|string  $payload
     */
    public function sign(array|string $payload, string $secret): string
    {
        $raw = is_array($payload)
            ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            : $payload;

        return 'sha256='.hash_hmac('sha256', $raw, $secret);
    }
}
