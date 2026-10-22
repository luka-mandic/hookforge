<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DeliveryFilters;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectDeliveryController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        $filters = DeliveryFilters::fromRequest($request);
        $search = trim($request->string('search')->toString());

        $query = $project->webhookDeliveries()
            ->getQuery()
            ->with(['endpoint', 'event']);

        $deliveries = $filters->apply($query)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('endpoint', fn ($endpointQuery) => $endpointQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('event', fn ($eventQuery) => $eventQuery->where('event_type', 'like', "%{$search}%"))
                        ->orWhere('last_error', 'like', "%{$search}%")
                        ->orWhere('response_status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        $endpoints = $project->webhookEndpoints()->orderBy('name')->get();

        return view('projects.deliveries.index', compact(
            'project',
            'deliveries',
            'search',
            'endpoints',
        ) + [
            'status' => $filters->status,
            'eventType' => $filters->eventType,
            'endpointId' => $filters->endpointId,
            'dateFrom' => $filters->dateFrom,
            'dateTo' => $filters->dateTo,
        ]);
    }
}
