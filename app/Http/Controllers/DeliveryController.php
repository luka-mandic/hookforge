<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $user = $request->user();

        $status = $request->string('status')->toString();
        $projectId = $request->string('project')->toString();
        $endpointId = $request->string('endpoint')->toString();
        $eventType = $request->string('event_type')->toString();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $projects = Project::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        $query = WebhookDelivery::query()
            ->with(['project', 'endpoint', 'event'])
            ->whereHas('project', fn ($projectQuery) => $projectQuery->where('user_id', $user->id))
            ->when($projectId !== '', fn ($searchQuery) => $searchQuery->where('project_id', $projectId))
            ->when($status !== '', fn ($searchQuery) => $searchQuery->where('status', $status))
            ->when($endpointId !== '', fn ($searchQuery) => $searchQuery->where('endpoint_id', $endpointId))
            ->when($eventType !== '', fn ($searchQuery) => $searchQuery->whereRelation('event', 'event_type', $eventType))
            ->when(is_string($dateFrom) && $dateFrom !== '', fn ($searchQuery) => $searchQuery->whereDate('created_at', '>=', $dateFrom))
            ->when(is_string($dateTo) && $dateTo !== '', fn ($searchQuery) => $searchQuery->whereDate('created_at', '<=', $dateTo))
            ->latest();

        $deliveries = $query->paginate(20)->appends($request->query());

        $endpoints = WebhookEndpoint::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->when($projectId !== '', fn ($searchQuery) => $searchQuery->where('project_id', $projectId))
            ->orderBy('name')
            ->get();

        return view('deliveries.index', compact(
            'deliveries',
            'projects',
            'endpoints',
            'status',
            'projectId',
            'endpointId',
            'eventType',
            'dateFrom',
            'dateTo',
        ));
    }

    public function show(WebhookDelivery $delivery): View
    {
        $this->authorize('view', $delivery->project);

        $delivery->load(['project', 'endpoint', 'event', 'attempts']);

        return view('deliveries.show', compact('delivery'));
    }
}
