<?php

namespace App\Services;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use App\Services\Concerns\ReadsAttributes;
use Illuminate\Support\Str;

class WebhookEndpointService
{
    use ReadsAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes): WebhookEndpoint
    {
        $name = $this->requiredString($attributes, 'name');
        $url = $this->requiredString($attributes, 'url');

        $endpoint = new WebhookEndpoint([
            'project_id' => $project->getKey(),
            'name' => $name,
            'url' => $url,
            'description' => $this->optionalString($attributes, 'description'),
            'status' => $this->optionalString($attributes, 'status', 'active'),
            'secret' => $this->generateSecret(),
        ]);

        $project->webhookEndpoints()->save($endpoint);

        return $endpoint->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WebhookEndpoint $endpoint, array $attributes): WebhookEndpoint
    {
        $endpoint->fill([
            'name' => $this->requiredString($attributes, 'name'),
            'url' => $this->requiredString($attributes, 'url'),
            'description' => $this->optionalString($attributes, 'description'),
            'status' => $this->optionalString($attributes, 'status', $endpoint->status),
        ]);

        $endpoint->save();

        return $endpoint->refresh();
    }

    public function toggle(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->status = $endpoint->status === 'active' ? 'disabled' : 'active';
        $endpoint->save();

        return $endpoint->refresh();
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
