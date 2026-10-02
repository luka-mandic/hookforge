<?php

namespace App\RabbitMq;

use InvalidArgumentException;

final class RabbitMqConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $user,
        public readonly string $password,
        public readonly string $vhost,
        public readonly string $exchange,
        public readonly string $routingKeyPrefix,
        public readonly string $queue,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $host = is_string($attributes['host'] ?? null) ? $attributes['host'] : 'rabbitmq';
        $port = isset($attributes['port']) && is_numeric($attributes['port']) ? (int) $attributes['port'] : 5672;
        $user = is_string($attributes['user'] ?? null) ? $attributes['user'] : 'guest';
        $password = is_string($attributes['password'] ?? null) ? $attributes['password'] : 'guest';
        $vhost = is_string($attributes['vhost'] ?? null) ? $attributes['vhost'] : '/';
        $exchange = is_string($attributes['exchange'] ?? null) && trim($attributes['exchange']) !== '' ? $attributes['exchange'] : 'hookforge.events';
        $routingKeyPrefix = is_string($attributes['routing_key_prefix'] ?? null) && trim($attributes['routing_key_prefix']) !== '' ? $attributes['routing_key_prefix'] : 'hookforge.delivery';
        $queue = is_string($attributes['queue'] ?? null) && trim($attributes['queue']) !== '' ? $attributes['queue'] : 'hookforge.delivery';

        return new self($host, $port, $user, $password, $vhost, $exchange, $routingKeyPrefix, $queue);
    }

    public static function fromEnvironment(): self
    {
        return self::fromArray(config('rabbitmq', []));
    }

    public function routingKeyFor(string $eventType): string
    {
        if (trim($eventType) === '') {
            throw new InvalidArgumentException('Event type cannot be empty.');
        }

        return sprintf('%s.%s', $this->routingKeyPrefix, $eventType);
    }
}
