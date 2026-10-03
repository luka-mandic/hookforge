@extends('layouts.dashboard')

@section('page-title', 'Delivery Dashboard')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Delivery dashboard</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">All deliveries</h1>
        </div>
        <a href="{{ route('dashboard') }}"
            class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
            Back to dashboard
        </a>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
        <form method="GET" action="{{ route('deliveries.index') }}" class="grid gap-3 xl:grid-cols-6">
            <div class="xl:col-span-1">
                <label for="project-filter"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Project</label>
                <select id="project-filter" name="project"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="">All projects</option>
                    @foreach ($projects as $projectOption)
                        <option value="{{ $projectOption->id }}" @selected($projectId === $projectOption->id)>
                            {{ $projectOption->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-1">
                <label for="status-filter"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Status</label>
                <select id="status-filter" name="status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="">All statuses</option>
                    <option value="queued" @selected($status === 'queued')>Queued</option>
                    <option value="processing" @selected($status === 'processing')>Processing</option>
                    <option value="delivered" @selected($status === 'delivered')>Delivered</option>
                    <option value="failed" @selected($status === 'failed')>Failed</option>
                    <option value="dead_lettered" @selected($status === 'dead_lettered')>Dead-lettered</option>
                </select>
            </div>

            <div class="xl:col-span-1">
                <label for="event-type-filter" class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Event
                    type</label>
                <input id="event-type-filter" name="event_type" type="text" value="{{ $eventType }}"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
                    placeholder="invoice.paid">
            </div>

            <div class="xl:col-span-1">
                <label for="endpoint-filter"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Endpoint</label>
                <select id="endpoint-filter" name="endpoint"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="">All endpoints</option>
                    @foreach ($endpoints as $endpointOption)
                        <option value="{{ $endpointOption->id }}" @selected($endpointId === $endpointOption->id)>
                            {{ $endpointOption->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="xl:col-span-1">
                <label for="date-from-filter"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">From</label>
                <input id="date-from-filter" name="date_from" type="date" value="{{ $dateFrom }}"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
            </div>

            <div class="xl:col-span-1">
                <label for="date-to-filter"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">To</label>
                <input id="date-to-filter" name="date_to" type="date" value="{{ $dateTo }}"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
            </div>

            <div class="xl:col-span-6 flex items-center justify-end gap-2">
                <button type="submit"
                    class="rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 hover:bg-sky-400">
                    Apply filters
                </button>
                <a href="{{ route('deliveries.index') }}"
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 hover:border-slate-500">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800 text-left text-sm text-slate-200">
                <thead class="bg-slate-950/80 text-xs uppercase tracking-[0.2em] text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Response</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($deliveries as $delivery)
                        <tr class="hover:bg-slate-950/70">
                            <td class="px-4 py-3 text-white">
                                <a href="{{ route('projects.show', $delivery->project) }}" class="hover:text-sky-200">
                                    {{ $delivery->project?->name ?? 'Unknown project' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ $delivery->endpoint?->name ?? 'Unknown endpoint' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $delivery->event?->event_type ?? 'Unknown event' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $deliveryTone = match ($delivery->status) {
                                        'delivered' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
                                        'failed' => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
                                        'dead_lettered' => 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-200',
                                        'processing' => 'border-sky-400/30 bg-sky-500/10 text-sky-200',
                                        default => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
                                    };
                                @endphp
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $deliveryTone }}">
                                    {{ str_replace('_', ' ', $delivery->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $delivery->response_status ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $delivery->created_at?->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">No deliveries matched the
                                current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($deliveries->hasPages())
        <div class="mt-6">
            {{ $deliveries->links() }}
        </div>
    @endif
@endsection
