@extends('layouts.dashboard')

@section('page-title', 'Dashboard')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Overview</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Dashboard</h1>
        </div>

        <a href="{{ route('projects.create') }}"
            class="inline-flex items-center justify-center rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 transition hover:bg-sky-400">
            New project
        </a>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($stats as $stat)
            @php
                $toneClasses = [
                    'sky' => 'border-sky-500/40 bg-sky-500/10 text-sky-200',
                    'emerald' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
                    'rose' => 'border-rose-500/40 bg-rose-500/10 text-rose-200',
                    'amber' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
                    'fuchsia' => 'border-fuchsia-500/40 bg-fuchsia-500/10 text-fuchsia-200',
                ][$stat['tone'] ?? 'sky'];
            @endphp

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-slate-950/20">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ $stat['label'] }}</p>
                    <span
                        class="rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $toneClasses }}">
                        live
                    </span>
                </div>
                <p class="mt-4 text-3xl font-semibold text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Recent events</h2>
                <a href="{{ route('projects.index') }}" class="text-sm text-sky-300 hover:text-sky-200">View all</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($recentEvents as $event)
                    @php
                        $eventBadge = match ($event->status) {
                            'delivered' => '✅ Delivered',
                            'failed' => '❌ Failed',
                            'dead_lettered' => '⚠️ Dead-lettered',
                            default => '⏳ Queued',
                        };
                    @endphp
                    <div
                        class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                        <div>
                            <p class="font-medium text-white">{{ $event->event_type }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $event->project?->name ?? 'Project' }} •
                                {{ $event->created_at?->diffForHumans() }}</p>
                        </div>
                        <span
                            class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-200">
                            {{ $eventBadge }}
                        </span>
                    </div>
                @empty
                    <div
                        class="rounded-xl border border-dashed border-slate-700 bg-slate-950/40 p-6 text-sm text-slate-400">
                        No events have been recorded yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Recent delivery attempts</h2>
                <a href="{{ route('projects.index') }}" class="text-sm text-sky-300 hover:text-sky-200">View all</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($recentDeliveries as $delivery)
                    <a href="{{ route('deliveries.show', $delivery) }}"
                        class="block rounded-xl border border-slate-800 bg-slate-950/60 p-3 transition hover:border-sky-500/60">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-white">{{ $delivery->event?->event_type ?? 'Unknown event' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">{{ $delivery->endpoint?->name ?? 'Endpoint' }} •
                                    {{ $delivery->created_at?->diffForHumans() }}</p>
                            </div>
                            <span
                                class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]
                                @if ($delivery->status === 'delivered') border-emerald-400/30 bg-emerald-500/10 text-emerald-200
                                @elseif ($delivery->status === 'failed') border-rose-400/30 bg-rose-500/10 text-rose-200
                                @elseif ($delivery->status === 'dead_lettered') border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-200
                                @else border-amber-400/30 bg-amber-500/10 text-amber-200 @endif">
                                {{ str_replace('_', ' ', $delivery->status) }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div
                        class="rounded-xl border border-dashed border-slate-700 bg-slate-950/40 p-6 text-sm text-slate-400">
                        No delivery attempts have been recorded yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
