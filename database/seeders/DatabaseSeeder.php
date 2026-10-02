<?php

namespace Database\Seeders;

use App\Models\DeliveryAttempt;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = User::factory()->count(3)->create();

        $users->each(function (User $user): void {
            $projects = Project::factory()->count(2)->for($user)->create();

            $projects->each(function (Project $project): void {
                $endpoints = WebhookEndpoint::factory()->count(2)->for($project)->create();

                foreach (['customer.created', 'invoice.paid', 'subscription.updated'] as $eventType) {
                    $event = WebhookEvent::factory()->for($project)->create([
                        'event_type' => $eventType,
                        'status' => 'queued',
                    ]);

                    $endpoints->each(function (WebhookEndpoint $endpoint) use ($project, $event): void {
                        WebhookSubscription::factory()->for($project)->for($endpoint)->create([
                            'event_type' => $event->event_type,
                            'status' => 'active',
                        ]);

                        $delivery = WebhookDelivery::factory()->for($project)->for($endpoint)->for($event)->create([
                            'status' => fake()->randomElement(['queued', 'succeeded', 'failed']),
                            'attempt_count' => fake()->numberBetween(1, 3),
                            'response_status' => fake()->randomElement([200, 201, 202, 500]),
                        ]);

                        DeliveryAttempt::factory()->for($delivery)->create([
                            'attempt_number' => 1,
                            'status' => $delivery->status === 'succeeded' ? 'succeeded' : 'failed',
                        ]);
                    });
                }
            });
        });
    }
}
