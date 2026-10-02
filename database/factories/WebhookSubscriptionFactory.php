<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'endpoint_id' => WebhookEndpoint::factory(),
            'event_type' => fake()->randomElement([
                'invoice.created',
                'payment.succeeded',
                'customer.created',
                'subscription.updated',
            ]),
            'status' => fake()->randomElement(['active', 'paused', 'disabled']),
        ];
    }
}
