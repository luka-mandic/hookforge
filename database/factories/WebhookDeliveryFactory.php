<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'endpoint_id' => WebhookEndpoint::factory()->for($project),
            'event_id' => WebhookEvent::factory()->for($project),
            'status' => fake()->randomElement(['queued', 'processing', 'succeeded', 'failed']),
            'attempt_count' => fake()->numberBetween(1, 3),
            'next_attempt_at' => null,
            'last_error' => null,
            'response_status' => fake()->optional()->numberBetween(200, 500),
            'sent_at' => now(),
            'delivered_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
