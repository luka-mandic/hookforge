@extends('layouts.dashboard')

@section('page-title', 'Projects')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Workspace</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Projects</h1>
        </div>

        <a href="{{ route('projects.create') }}"
            class="inline-flex items-center justify-center rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 transition hover:bg-sky-400">
            New project
        </a>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $project)
            <a href="{{ route('projects.show', $project) }}"
                class="group block rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-lg shadow-slate-950/20 transition hover:border-sky-500/60 hover:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-400">Project</p>
                        <h2 class="mt-2 text-xl font-semibold text-white">{{ $project->name }}</h2>
                    </div>

                    <span
                        class="rounded-full border border-sky-400/30 bg-sky-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-sky-200">
                        {{ $project->status }}
                    </span>
                </div>

                <p class="mt-4 line-clamp-3 text-sm text-slate-300">
                    {{ $project->description ?: 'No description provided yet.' }}
                </p>

                <div class="mt-6 flex items-center justify-between border-t border-slate-800 pt-4 text-xs text-slate-400">
                    <span>{{ $project->webhookEvents()->count() }} events</span>
                    <span>{{ $project->webhookDeliveries()->count() }} deliveries</span>
                </div>
            </a>
        @empty
            <div
                class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 p-8 text-slate-300 md:col-span-2 xl:col-span-3">
                No projects yet. Create your first project to start receiving webhook events.
            </div>
        @endforelse
    </div>
@endsection
