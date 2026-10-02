<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->company().' Webhook',
            'url' => fake()->url(),
            'secret' => sha1((string) fake()->uuid()),
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['active', 'paused', 'disabled']),
        ];
    }
}
