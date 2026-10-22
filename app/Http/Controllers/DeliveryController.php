<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\DeliveryFilters;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $userId = $this->authenticatedUser($request)->id;
        $filters = DeliveryFilters::fromRequest($request);
        $projectId = $request->string('project')->toString();

        $projects = Project::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();

        $query = WebhookDelivery::query()
            ->with(['project', 'endpoint', 'event'])
            ->whereHas('project', fn ($projectQuery) => $projectQuery->where('user_id', $userId))
            ->when($projectId !== '', fn ($searchQuery) => $searchQuery->where('project_id', $projectId))
            ->latest();

        $deliveries = $filters->apply($query)->paginate(20)->appends($request->query());

        $endpoints = WebhookEndpoint::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->when($projectId !== '', fn ($searchQuery) => $searchQuery->where('project_id', $projectId))
            ->orderBy('name')
            ->get();

        return view('deliveries.index', compact(
            'deliveries',
            'projects',
            'endpoints',
            'projectId',
        ) + [
            'status' => $filters->status,
            'endpointId' => $filters->endpointId,
            'eventType' => $filters->eventType,
            'dateFrom' => $filters->dateFrom,
            'dateTo' => $filters->dateTo,
        ]);
    }

    public function show(WebhookDelivery $delivery): View
    {
        $this->authorize('view', $delivery->project);

        $delivery->load(['project', 'endpoint', 'event', 'attempts']);

        return view('deliveries.show', compact('delivery'));
    }
}
