@extends('layouts.dashboard')

@section('page-title', $project->name)

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Project overview</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ $project->name }}</h1>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('projects.events.index', $project) }}"
                class="inline-flex items-center rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
                Events
            </a>
            <a href="{{ route('projects.deliveries.index', $project) }}"
                class="inline-flex items-center rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
                Deliveries
            </a>
            <a href="{{ route('projects.edit', $project) }}"
                class="inline-flex items-center rounded-xl bg-sky-500 px-3 py-2 text-sm font-medium text-slate-950 hover:bg-sky-400">
                Edit project
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
            <p class="mt-3 text-2xl font-semibold text-white">{{ ucfirst($project->status) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Events</p>
            <p class="mt-3 text-2xl font-semibold text-white">{{ $project->webhookEvents()->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Deliveries</p>
            <p class="mt-3 text-2xl font-semibold text-white">{{ $project->webhookDeliveries()->count() }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-white">Webhook endpoints</h2>
                <span class="text-sm text-slate-400">{{ $project->webhookEndpoints()->count() }} total</span>
            </div>

            <div class="mt-5 space-y-4">
                @forelse ($project->webhookEndpoints as $endpoint)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-base font-medium text-white">{{ $endpoint->name }}</h3>
                                <p class="mt-1 break-all text-sm text-slate-400">{{ $endpoint->url }}</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] {{ $endpoint->status === 'active' ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200' : 'border-amber-400/40 bg-amber-500/10 text-amber-200' }}">
                                    {{ $endpoint->status }}
                                </span>

                                <form method="POST"
                                    action="{{ route('projects.endpoints.toggle', [$project, $endpoint]) }}">
                                    @csrf
                                    <button type="submit"
                                        class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-200 hover:border-sky-500/60 hover:text-white">
                                        {{ $endpoint->status === 'active' ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="rounded-xl border border-dashed border-slate-700 bg-slate-950/40 p-6 text-sm text-slate-400">
                        No endpoints configured for this project yet.
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
            <h2 class="text-lg font-semibold text-white">Add endpoint</h2>

            <form method="POST" action="{{ route('projects.endpoints.store', $project) }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="endpoint_name" class="mb-1.5 block text-sm text-slate-300">Name</label>
                    <input id="endpoint_name" name="name" type="text" value="{{ old('name') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none">
                </div>

                <div>
                    <label for="endpoint_url" class="mb-1.5 block text-sm text-slate-300">URL</label>
                    <input id="endpoint_url" name="url" type="url" value="{{ old('url') }}"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none">
                </div>

                <div>
                    <label for="endpoint_status" class="mb-1.5 block text-sm text-slate-300">Status</label>
                    <select id="endpoint_status" name="status"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                        <option value="active">active</option>
                        <option value="disabled">disabled</option>
                    </select>
                </div>

                <div>
                    <label for="endpoint_description" class="mb-1.5 block text-sm text-slate-300">Description</label>
                    <textarea id="endpoint_description" name="description" rows="3"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none">{{ old('description') }}</textarea>
                </div>

                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 hover:bg-sky-400">
                    Create endpoint
                </button>
            </form>
        </aside>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-sky-300">Webhook testing</p>
                <h2 class="mt-2 text-lg font-semibold text-white">Simulated endpoint</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('projects.test-endpoints.store', $project) }}"
            class="mt-5 grid gap-4 md:grid-cols-3">
            @csrf

            <div>
                <label for="response_status" class="mb-1.5 block text-sm text-slate-300">Response</label>
                <select id="response_status" name="response_status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white focus:border-sky-500 focus:outline-none">
                    <option value="200">200 OK</option>
                    <option value="201">201 Created</option>
                    <option value="400">400 Bad request</option>
                    <option value="401">401 Unauthorized</option>
                    <option value="404">404 Not found</option>
                    <option value="429">429 Too many requests</option>
                    <option value="500">500 Server error</option>
                    <option value="timeout">Timeout</option>
                </select>
            </div>

            <div>
                <label for="delay_ms" class="mb-1.5 block text-sm text-slate-300">Delay (ms)</label>
                <input id="delay_ms" name="delay_ms" type="number" min="0" max="1500" value="250"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none">
            </div>

            <div class="flex items-end">
                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 hover:bg-sky-400">
                    Create test endpoint
                </button>
            </div>
        </form>

        @php
            $simulatedEndpoints = $project
                ->webhookEndpoints()
                ->where('name', 'like', 'Test endpoint%')
                ->latest()
                ->get();
        @endphp

        @if ($simulatedEndpoints->isNotEmpty())
            <div class="mt-5 space-y-3">
                @foreach ($simulatedEndpoints as $simulatedEndpoint)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="font-medium text-white">{{ $simulatedEndpoint->name }}</p>
                                <p class="text-sm text-slate-400 break-all">{{ $simulatedEndpoint->url }}</p>
                            </div>
                            <span
                                class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-200">
                                active
                            </span>
                        </div>

                        <p class="mt-3 text-sm text-slate-300">{{ $simulatedEndpoint->description }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
