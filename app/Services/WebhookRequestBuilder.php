<?php

namespace App\Services;

use App\Models\WebhookDelivery;

final class WebhookRequestBuilder
{
    public function __construct(
        protected ?WebhookSigner $signer = null,
    ) {
        $this->signer ??= new WebhookSigner;
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, body: string}
     */
    public function build(WebhookDelivery $delivery): array
    {
        $event = $delivery->event()->firstOrFail();
        $endpoint = $delivery->endpoint()->firstOrFail();
        $payload = $event->payload ?? [];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'method' => 'POST',
            'url' => $endpoint->url,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'hookforge/1.0',
                'X-Hookforge-Event' => $event->event_type,
                'X-Hookforge-Delivery' => $delivery->getKey(),
                'X-Hookforge-Signature' => $this->signer->sign($payload, $endpoint->secret ?? ''),
            ],
            'body' => $body,
        ];
    }
}
