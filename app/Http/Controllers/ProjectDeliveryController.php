<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectDeliveryController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        $status = $request->string('status')->toString();
        $search = trim((string) $request->query('search', ''));
        $eventType = $request->string('event_type')->toString();
        $endpointId = $request->string('endpoint')->toString();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $deliveries = $project->webhookDeliveries()
            ->with(['endpoint', 'event'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($eventType !== '', fn ($query) => $query->whereRelation('event', 'event_type', $eventType))
            ->when($endpointId !== '', fn ($query) => $query->where('endpoint_id', $endpointId))
            ->when(is_string($dateFrom) && $dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when(is_string($dateTo) && $dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
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
            'status',
            'search',
            'eventType',
            'endpointId',
            'dateFrom',
            'dateTo',
            'endpoints',
        ));
    }
}
