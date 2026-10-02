<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->is($endpoint->project->user);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->is($endpoint->project->user);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->is($endpoint->project->user);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }
}
