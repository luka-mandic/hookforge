<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookEndpointService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(Project $project, array $attributes): WebhookEndpoint
    {
        $name = (string) $attributes['name'];
        $url = (string) $attributes['url'];

        $endpoint = new WebhookEndpoint([
            'project_id' => $project->getKey(),
            'name' => $name,
            'url' => $url,
            'description' => isset($attributes['description']) && is_string($attributes['description']) ? $attributes['description'] : null,
            'status' => isset($attributes['status']) && is_string($attributes['status']) ? $attributes['status'] : 'active',
            'secret' => $this->generateSecret(),
        ]);

        $project->webhookEndpoints()->save($endpoint);

        return $endpoint->fresh();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(WebhookEndpoint $endpoint, array $attributes): WebhookEndpoint
    {
        $endpoint->fill([
            'name' => (string) $attributes['name'],
            'url' => (string) $attributes['url'],
            'description' => array_key_exists('description', $attributes) && is_string($attributes['description']) ? $attributes['description'] : null,
            'status' => array_key_exists('status', $attributes) && is_string($attributes['status']) ? $attributes['status'] : $endpoint->status,
        ]);

        $endpoint->save();

        return $endpoint->fresh();
    }

    public function toggle(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->status = $endpoint->status === 'active' ? 'disabled' : 'active';
        $endpoint->save();

        return $endpoint->fresh();
    }

    public function delete(WebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    protected function generateSecret(): string
    {
        return Str::random(32);
    }
}
