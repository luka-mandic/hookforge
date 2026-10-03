<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title', 'Hookforge')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen" x-data="{ sidebarOpen: false }">
        <div class="fixed inset-0 z-40 bg-slate-950/70 lg:hidden" x-show="sidebarOpen" x-cloak
            @click="sidebarOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full border-r border-slate-800 bg-slate-950/95 p-5 backdrop-blur transition lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-500 font-bold text-slate-950">
                        H</div>
                    <div>
                        <p class="text-lg font-semibold text-white">Hookforge</p>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Webhooks</p>
                    </div>
                </div>

                <button type="button" class="rounded-lg border border-slate-700 p-2 text-slate-300 lg:hidden"
                    @click="sidebarOpen = false">
                    ✕
                </button>
            </div>

            <nav class="mt-8 space-y-2">
                <a href="{{ route('dashboard') }}"
                    class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2.5 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
                    Dashboard
                </a>
                <a href="{{ route('projects.index') }}"
                    class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2.5 text-sm text-slate-200 hover:border-sky-500/60 hover:text-white">
                    Projects
                </a>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/80 backdrop-blur">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-lg border border-slate-700 p-2 text-slate-200 lg:hidden"
                            @click="sidebarOpen = !sidebarOpen">
                            ☰
                        </button>
                        <h1 class="text-lg font-semibold text-white">@yield('page-title', 'Dashboard')</h1>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-500 text-xs font-semibold text-slate-950">
                            {{ auth()->user()->initials() }}
                        </div>
                        <div class="hidden text-left sm:block">
                            <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                            <p class="text-[11px] text-slate-400">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>
