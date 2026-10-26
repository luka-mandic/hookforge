@extends('layouts.dashboard')

@section('page-title', 'Create project')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('projects.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Projects</a>

        <p class="mt-4 text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Workspace</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Create project</h1>

        @if ($errors->any())
            <div class="mt-6 rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}"
            class="mt-6 space-y-5 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-lg shadow-slate-950/20">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-200">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}"
                    class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-200">Description</label>
                <textarea id="description" name="description" rows="4"
                    class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">{{ old('description') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('projects.index') }}" class="text-sm text-slate-400 hover:text-white">Cancel</a>
                <button type="submit"
                    class="rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 transition hover:bg-sky-400">
                    Create project
                </button>
            </div>
        </form>
    </div>
@endsection
