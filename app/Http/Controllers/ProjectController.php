<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $projects = auth()->user()->projects()->latest()->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request, ProjectService $service): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $service->create(auth()->user(), $request->validated());

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project created successfully.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load('webhookEndpoints');

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectService $service): RedirectResponse
    {
        $this->authorize('update', $project);

        $service->update($project, $request->validated());

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project updated successfully.');
    }

    public function destroy(Project $project, ProjectService $service): RedirectResponse
    {
        $this->authorize('delete', $project);

        $service->delete($project);

        return redirect()->route('projects.index')
            ->with('status', 'Project deleted successfully.');
    }
}
