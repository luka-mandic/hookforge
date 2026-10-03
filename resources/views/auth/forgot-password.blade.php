<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hookforge · Forgot password</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-full items-center justify-center bg-slate-950 px-4 py-12 text-slate-100">
    <div class="w-full max-w-lg rounded-3xl border border-slate-800 bg-slate-900/90 p-8 shadow-2xl shadow-slate-950/60">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Account recovery</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Forgot password?</h1>
        <p class="mt-3 text-sm text-slate-400">Password reset is intentionally simplified for a recruiter-facing demo.
            Use the built-in test account or return to the sign-in screen.</p>

        <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
            Demo access: <span class="font-medium text-slate-200">demo@hookforge.test</span> / <span
                class="font-medium text-slate-200">password</span>
        </div>

        <div class="mt-6 flex items-center justify-between gap-3">
            <a href="{{ route('login') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-500">
                Back to sign in
            </a>
        </div>
    </div>
</body>

</html>
