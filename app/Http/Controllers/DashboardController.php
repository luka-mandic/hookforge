<?php

namespace App\Http\Controllers;

use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $this->authenticatedUser($request)->id;

        $ownedByUser = fn (Builder $query): Builder => $query->where('user_id', $userId);

        // One grouped query replaces a COUNT per project and status.
        $deliveryCounts = WebhookDelivery::query()
            ->whereHas('project', $ownedByUser)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $eventCount = WebhookEvent::query()->whereHas('project', $ownedByUser)->count();

        $count = fn (string ...$statuses): int => array_sum(array_map(fn (string $status): int => is_numeric($deliveryCounts[$status] ?? null) ? (int) $deliveryCounts[$status] : 0, $statuses));

        $stats = [
            ['label' => 'Total events', 'value' => (string) $eventCount, 'tone' => 'sky'],
            ['label' => 'Successful deliveries', 'value' => (string) $count('delivered'), 'tone' => 'emerald'],
            ['label' => 'Failed deliveries', 'value' => (string) $count('failed'), 'tone' => 'rose'],
            ['label' => 'Pending deliveries', 'value' => (string) $count('queued', 'processing'), 'tone' => 'amber'],
            ['label' => 'Dead-lettered deliveries', 'value' => (string) $count('dead_lettered'), 'tone' => 'fuchsia'],
        ];

        $recentEvents = WebhookEvent::query()
            ->whereHas('project', $ownedByUser)
            ->latest()
            ->limit(5)
            ->get();

        $recentDeliveries = WebhookDelivery::query()
            ->whereHas('project', $ownedByUser)
            ->with(['endpoint', 'event'])
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard', compact('stats', 'recentEvents', 'recentDeliveries'));
    }
}
