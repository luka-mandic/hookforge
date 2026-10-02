<?php

namespace App\Livewire;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class WebhookEndpointList extends Component
{
    public Project $project;

    public function render(): View
    {
        return view('livewire.webhook-endpoint-list', [
            'project' => $this->project->load('webhookEndpoints'),
        ]);
    }
}
