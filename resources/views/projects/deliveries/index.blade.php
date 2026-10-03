@extends('layouts.dashboard')

@section('page-title', $project->name . ' · Deliveries')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Project deliveries</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $project->name }}</h1>
        </div>
        <a href="{{ route('projects.show', $project) }}"
            class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
            Back to project
        </a>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
        <form method="GET" action="{{ route('projects.deliveries.index', $project) }}"
            class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label for="delivery-search"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Search</label>
                <input id="delivery-search" name="search" value="{{ $search }}" type="search"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
                    placeholder="Search endpoint, event, or error text">
            </div>

            <div class="lg:w-52">
                <label for="delivery-status"
                    class="mb-1.5 block text-xs uppercase tracking-[0.2em] text-slate-400">Status</label>
                <select id="delivery-status" name="status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="">All statuses</option>
                    <option value="queued" @selected($status === 'queued')>Queued</option>
                    <option value="processing" @selected($status === 'processing')>Processing</option>
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
                <a href="{{ route('projects.deliveries.index', $project) }}"
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
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Attempts</th>
                        <th class="px-4 py-3">Response</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($deliveries as $delivery)
                        <tr class="hover:bg-slate-950/70">
                            <td class="px-4 py-3">
                                <a href="{{ route('deliveries.show', $delivery) }}"
                                    class="font-medium text-white hover:text-sky-200">
                                    {{ $delivery->endpoint?->name ?? 'Unknown endpoint' }}
                                </a>
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
                            <td class="px-4 py-3 text-slate-300">{{ $delivery->attempt_count }}</td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ $delivery->response_status ? $delivery->response_status : '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $delivery->created_at?->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400">No deliveries matched the
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
