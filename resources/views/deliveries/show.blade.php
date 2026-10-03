@extends('layouts.dashboard')

@section('page-title', 'Delivery details')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Delivery details</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $delivery->event?->event_type ?? 'Webhook delivery' }}</h1>
        </div>
        <a href="{{ route('projects.deliveries.index', $delivery->project) }}"
            class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
            Back to deliveries
        </a>
    </div>

    @php
        $deliveryStatusClass = match ($delivery->status) {
            'delivered' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
            'failed' => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
            'dead_lettered' => 'border-fuchsia-400/30 bg-fuchsia-500/10 text-fuchsia-200',
            'processing' => 'border-sky-400/30 bg-sky-500/10 text-sky-200',
            default => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
        };
    @endphp

    <div class="mt-6 grid gap-5 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
            <div
                class="mt-3 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $deliveryStatusClass }}">
                {{ str_replace('_', ' ', $delivery->status) }}
            </div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Attempts</p>
            <p class="mt-3 text-xl font-semibold text-white">{{ $delivery->attempt_count }}</p>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Response</p>
            <p class="mt-3 text-xl font-semibold text-white">{{ $delivery->response_status ?: '—' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Endpoint</p>
            <p class="mt-3 text-xl font-semibold text-white">{{ $delivery->endpoint?->name ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <h2 class="text-lg font-semibold text-white">Delivery metadata</h2>
            <dl class="mt-5 space-y-4 text-sm text-slate-300">
                <div class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
                    <dt class="text-slate-400">Project</dt>
                    <dd class="font-medium text-white">{{ $delivery->project->name }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
                    <dt class="text-slate-400">Endpoint URL</dt>
                    <dd class="break-all text-right text-white">{{ $delivery->endpoint?->url ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
                    <dt class="text-slate-400">Created</dt>
                    <dd class="text-white">{{ $delivery->created_at?->format('M j, Y H:i') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3">
                    <dt class="text-slate-400">Last error</dt>
                    <dd class="text-right text-white">{{ $delivery->last_error ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <h2 class="text-lg font-semibold text-white">Attempt history</h2>

            <div class="mt-5 space-y-3">
                @forelse ($delivery->attempts as $attempt)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-medium text-white">Attempt {{ $attempt->attempt_number }}</p>
                            <span
                                class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-200">
                                {{ $attempt->status }}
                            </span>
                        </div>
                        <div class="mt-3 grid gap-2 text-sm text-slate-300 md:grid-cols-3">
                            <div>
                                <p class="text-slate-400">HTTP</p>
                                <p class="mt-1 font-medium text-white">{{ $attempt->http_status ?: '—' }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">Started</p>
                                <p class="mt-1 font-medium text-white">
                                    {{ $attempt->started_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">Completed</p>
                                <p class="mt-1 font-medium text-white">
                                    {{ $attempt->completed_at?->format('M j, Y H:i') ?? '—' }}</p>
                            </div>
                        </div>
                        @if ($attempt->error_message)
                            <p class="mt-3 text-sm text-rose-200">{{ $attempt->error_message }}</p>
                        @endif
                    </div>
                @empty
                    <div
                        class="rounded-xl border border-dashed border-slate-700 bg-slate-950/40 p-6 text-sm text-slate-400">
                        No attempts recorded for this delivery yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
