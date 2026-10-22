<?php

namespace App\Console\Commands;

use App\RabbitMq\DeliveryConsumer;
use App\RabbitMq\RabbitMqConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('webhooks:work')]
#[Description('Consume webhook deliveries from RabbitMQ. Run any number of these in parallel.')]
class ConsumeDeliveries extends Command
{
    public function handle(DeliveryConsumer $consumer): int
    {
        $config = RabbitMqConfig::fromEnvironment();

        $this->info("Consuming queue [{$config->queue}] on {$config->host}:{$config->port}");

        $consumer->consume($config);

        $this->info('Worker stopped.');

        return self::SUCCESS;
    }
}
