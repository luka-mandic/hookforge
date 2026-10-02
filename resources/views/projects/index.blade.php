<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects</title>
</head>

<body>
    <main>
        <h1>Projects</h1>

        @if (session('status'))
            <p>{{ session('status') }}</p>
        @endif

        <a href="{{ route('projects.create') }}">Create project</a>

        @livewire('project-list')
    </main>
</body>

</html>
