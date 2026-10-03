<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectEventController extends Controller
{
    use AuthorizesRequests;

    public function index(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        $status = $request->string('status')->toString();
        $search = trim((string) $request->query('search', ''));

        $events = $project->webhookEvents()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('event_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('idempotency_key', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return view('projects.events.index', compact('project', 'events', 'status', 'search'));
    }
}
