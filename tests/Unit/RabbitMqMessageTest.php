<?php

use App\Models\WebhookEvent;
use App\RabbitMq\EventPublisher;
use App\RabbitMq\MessageTransport;
use App\RabbitMq\RabbitMqConfig;
use App\RabbitMq\WebhookMessage;

it('builds a webhook message with explicit routing information', function () {
    $event = WebhookEvent::factory()->make([
        'id' => '01J9P6M1A8T4M7V0K5N2Q4R9A1',
        'project_id' => '01J9P6M1A8T4M7V0K5N2Q4R9A2',
        'event_type' => 'order.created',
        'payload' => [
            'order_id' => 'ord_123',
            'amount' => 2000,
        ],
    ]);

    $message = WebhookMessage::fromEvent($event, RabbitMqConfig::fromArray([
        'exchange' => 'hookforge.events',
        'routing_key_prefix' => 'hookforge.delivery',
    ]));

    expect($message->routingKey)->toBe('hookforge.delivery.order.created')
        ->and($message->payload)->toBe([
            'order_id' => 'ord_123',
            'amount' => 2000,
        ])
        ->and($message->projectId)->toBe('01J9P6M1A8T4M7V0K5N2Q4R9A2');
});

it('rejects invalid message data', function () {
    WebhookMessage::fromPayload([
        'project_id' => 'project_123',
        'event_id' => 'event_123',
        'event_type' => 'order.created',
        'payload' => [],
    ], RabbitMqConfig::fromArray([
        'exchange' => 'hookforge.events',
        'routing_key_prefix' => 'hookforge.delivery',
    ]));
})->throws(InvalidArgumentException::class);

it('publishes a valid message through the configured transport', function () {
    $transport = new class implements MessageTransport
    {
        public array $messages = [];

        public function publish(WebhookMessage $message, RabbitMqConfig $config): void
        {
            $this->messages[] = [
                'routing_key' => $message->routingKey,
                'payload' => $message->payload,
                'exchange' => $config->exchange,
            ];
        }
    };

    $publisher = new EventPublisher(
        RabbitMqConfig::fromArray([
            'exchange' => 'hookforge.events',
            'routing_key_prefix' => 'hookforge.delivery',
        ]),
        $transport,
    );

    $event = WebhookEvent::factory()->make([
        'project_id' => 'project_123',
        'id' => 'event_123',
        'event_type' => 'invoice.paid',
        'payload' => ['invoice_id' => 'inv_42'],
    ]);

    $publisher->publish($event);

    expect($transport->messages)->toHaveCount(1)
        ->and($transport->messages[0]['routing_key'])->toBe('hookforge.delivery.invoice.paid')
        ->and($transport->messages[0]['exchange'])->toBe('hookforge.events');
});
