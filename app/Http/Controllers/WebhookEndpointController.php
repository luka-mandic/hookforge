<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookEndpointRequest;
use App\Http\Requests\UpdateWebhookEndpointRequest;
use App\Models\Project;
use App\Models\WebhookEndpoint;
use App\Services\WebhookEndpointService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class WebhookEndpointController extends Controller
{
    use AuthorizesRequests;
    public function store(Project $project, StoreWebhookEndpointRequest $request, WebhookEndpointService $service): RedirectResponse
    {
        $this->authorize('create', [WebhookEndpoint::class, $project]);

        $service->create($project, $request->validated());

        return redirect()->route('projects.show', $project)
            ->with('status', 'Webhook endpoint created successfully.');
    }

    public function update(Project $project, WebhookEndpoint $endpoint, UpdateWebhookEndpointRequest $request, WebhookEndpointService $service): RedirectResponse
    {
        $this->authorize('update', $endpoint);

        $service->update($endpoint, $request->validated());

        return redirect()->route('projects.show', $project)
            ->with('status', 'Webhook endpoint updated successfully.');
    }

    public function destroy(Project $project, WebhookEndpoint $endpoint, WebhookEndpointService $service): RedirectResponse
    {
        $this->authorize('delete', $endpoint);

        $service->delete($endpoint);

        return redirect()->route('projects.show', $project)
            ->with('status', 'Webhook endpoint deleted successfully.');
    }

    public function toggle(Project $project, WebhookEndpoint $endpoint, WebhookEndpointService $service): RedirectResponse
    {
        $this->authorize('update', $endpoint);

        $service->toggle($endpoint);

        return redirect()->route('projects.show', $project)
            ->with('status', 'Webhook endpoint status updated successfully.');
    }
}
