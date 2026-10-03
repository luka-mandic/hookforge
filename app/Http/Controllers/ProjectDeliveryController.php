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

        $deliveries = $project->webhookDeliveries()
            ->with(['endpoint', 'event'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
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

        return view('projects.deliveries.index', compact('project', 'deliveries', 'status', 'search'));
    }
}
