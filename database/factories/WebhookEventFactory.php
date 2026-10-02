<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'event_type' => fake()->randomElement([
                'customer.created',
                'invoice.paid',
                'payment.failed',
                'subscription.updated',
            ]),
            'payload' => [
                'id' => fake()->uuid(),
                'amount' => fake()->randomNumber(3),
                'currency' => fake()->currencyCode(),
                'status' => fake()->randomElement(['pending', 'succeeded', 'failed']),
            ],
            'status' => fake()->randomElement(['queued', 'delivered', 'failed']),
        ];
    }
}
