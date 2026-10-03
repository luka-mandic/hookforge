@extends('layouts.dashboard')

@section('page-title', $project->name . ' · Events')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Project events</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $project->name }}</h1>
        </div>
        <a href="{{ route('projects.show', $project) }}"
            class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
            Back to project
        </a>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
        <form method="GET" action="{{ route('projects.events.index', $project) }}"
            class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label for="event-search"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Search</label>
                <input id="event-search" name="search" value="{{ $search }}" type="search"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
                    placeholder="Search event type or idempotency key">
            </div>

            <div class="lg:w-52">
                <label for="event-status"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Status</label>
                <select id="event-status" name="status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="">All statuses</option>
                    <option value="queued" @selected($status === 'queued')>Queued</option>
                    <option value="delivered" @selected($status === 'delivered')>Delivered</option>
                    <option value="failed" @selected($status === 'failed')>Failed</option>
                    <option value="dead_lettered" @selected($status === 'dead_lettered')>Dead-lettered</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 hover:bg-sky-400">
                    Apply
                </button>
                <a href="{{ route('projects.events.index', $project) }}"
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
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Payload</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($events as $event)
                        <tr class="hover:bg-slate-950/70">
                            <td class="px-4 py-3 font-medium text-white">{{ $event->event_type }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $eventTone = match ($event->status) {
                                        'delivered' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
                                        'failed' => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
                                        'dead_lettered' => 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-200',
                                        default => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
                                    };
                                @endphp
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $eventTone }}">
                                    {{ str_replace('_', ' ', $event->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $event->created_at?->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">No events matched the current
                                filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($events->hasPages())
        <div class="mt-6">
            {{ $events->links() }}
        </div>
    @endif
@endsection
