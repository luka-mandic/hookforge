<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->owns($user, $endpoint->project);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->owns($user, $endpoint->project);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->owns($user, $endpoint->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    private function owns(User $user, ?Project $project): bool
    {
        return $project !== null && $user->is($project->user);
    }
}
