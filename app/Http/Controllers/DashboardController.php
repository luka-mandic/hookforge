<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $projects = auth()->user()->projects()->with(['webhookEvents', 'webhookDeliveries'])->latest()->get();

        $stats = [
            [
                'label' => 'Total events',
                'value' => (string) $projects->sum(fn (Project $project) => $project->webhookEvents()->count()),
                'tone' => 'sky',
            ],
            [
                'label' => 'Successful deliveries',
                'value' => (string) $projects->sum(fn (Project $project) => $project->webhookDeliveries()->where('status', 'delivered')->count()),
                'tone' => 'emerald',
            ],
            [
                'label' => 'Failed deliveries',
                'value' => (string) $projects->sum(fn (Project $project) => $project->webhookDeliveries()->where('status', 'failed')->count()),
                'tone' => 'rose',
            ],
            [
                'label' => 'Pending deliveries',
                'value' => (string) $projects->sum(fn (Project $project) => $project->webhookDeliveries()->whereIn('status', ['queued', 'processing'])->count()),
                'tone' => 'amber',
            ],
            [
                'label' => 'Dead-lettered deliveries',
                'value' => (string) $projects->sum(fn (Project $project) => $project->webhookDeliveries()->where('status', 'dead_lettered')->count()),
                'tone' => 'fuchsia',
            ],
        ];

        $recentEvents = $projects
            ->flatMap(fn (Project $project) => $project->webhookEvents()->latest()->limit(5)->get())
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        $recentDeliveries = $projects
            ->flatMap(fn (Project $project) => $project->webhookDeliveries()->with(['endpoint', 'event'])->latest()->limit(8)->get())
            ->sortByDesc('created_at')
            ->take(8)
            ->values();

        return view('dashboard', compact('projects', 'stats', 'recentEvents', 'recentDeliveries'));
    }
}
