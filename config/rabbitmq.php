<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'hookforge.events'),
    'queue' => env('RABBITMQ_QUEUE', 'hookforge.delivery'),
    'routing_key_prefix' => env('RABBITMQ_ROUTING_KEY_PREFIX', 'hookforge.delivery'),
];
