<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hookforge · Sign in</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-full items-center justify-center bg-slate-950 px-4 py-12 text-slate-100">
    <div
        class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/60 backdrop-blur">
        <div class="border-b border-slate-800 bg-slate-950/70 px-6 py-5">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500 text-lg font-bold text-slate-950">
                    H</div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.2em] text-sky-300">Hookforge</p>
                    <p class="text-sm text-slate-400">Webhook operations</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-6">
            <div class="mb-6">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Welcome back</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Sign in</h1>
                <p class="mt-2 text-sm text-slate-400">Access your webhook dashboard and delivery insights.</p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm text-slate-300">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
                        placeholder="you@example.com">
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label for="password" class="text-sm text-slate-300">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-sky-300 hover:text-sky-200">Forgot
                            password?</a>
                    </div>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                        <input type="checkbox" name="remember"
                            class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-sky-500 focus:ring-sky-500">
                        Remember me
                    </label>

                    <a href="{{ route('register') }}" class="text-sm text-sky-300 hover:text-sky-200">Create account</a>
                </div>

                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-sky-500 px-4 py-2.5 text-sm font-medium text-slate-950 transition hover:bg-sky-400">
                    Sign in
                </button>
            </form>

            <form method="POST" action="{{ route('login.test-user') }}" class="mt-5">
                @csrf
                <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-sky-500/40 bg-sky-500/10 px-4 py-2.5 text-sm font-medium text-sky-200 transition hover:bg-sky-500/20">
                    Login as test user
                </button>
            </form>

            <div class="mt-5 rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-xs text-slate-400">
                Demo account: <span class="font-medium text-slate-200">demo@hookforge.test</span> / <span
                    class="font-medium text-slate-200">password</span>
            </div>
        </div>
    </div>
</body>

</html>
