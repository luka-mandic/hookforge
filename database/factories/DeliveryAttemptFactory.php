<?php

namespace Database\Factories;

use App\Models\DeliveryAttempt;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAttempt>
 */
class DeliveryAttemptFactory extends Factory
{
    protected $model = DeliveryAttempt::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['queued', 'succeeded', 'failed']);

        return [
            'delivery_id' => WebhookDelivery::factory(),
            'attempt_number' => 1,
            'status' => $status,
            'http_status' => $status === 'succeeded' ? 200 : fake()->randomElement([500, 502, 503]),
            'response_body' => $status === 'succeeded' ? '{"ok":true}' : '{"error":"delivery failed"}',
            'error_message' => $status === 'failed' ? fake()->sentence() : null,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}
