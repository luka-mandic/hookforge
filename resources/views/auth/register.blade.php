<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hookforge · Register</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-full items-center justify-center bg-slate-950 px-4 py-12 text-slate-100">
    <div class="w-full max-w-lg rounded-3xl border border-slate-800 bg-slate-900/90 p-8 shadow-2xl shadow-slate-950/60">
        <p class="text-sm font-medium uppercase tracking-[0.2em] text-sky-300">Recruiter access</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Create account</h1>
        <p class="mt-3 text-sm text-slate-400">This demo is designed for quick evaluation, so the recommended path is to
            use the built-in test user below.</p>

        <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-950/60 p-4 text-sm text-slate-300">
            For a friction-free demo experience, use the <a href="{{ route('login') }}"
                class="font-medium text-sky-300 hover:text-sky-200">Login as test user</a> shortcut on the sign-in page.
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
