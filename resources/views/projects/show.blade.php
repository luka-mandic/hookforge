<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->name }}</title>
</head>

<body>
    <main>
        <h1>{{ $project->name }}</h1>

        @if (session('status'))
            <p>{{ session('status') }}</p>
        @endif

        <p>{{ $project->description ?? 'No description provided.' }}</p>

        <a href="{{ route('projects.index') }}">Back to projects</a>

        @livewire('webhook-endpoint-list', ['project' => $project])

        <h2>Add endpoint</h2>

        <form method="POST" action="{{ route('projects.endpoints.store', $project) }}">
            @csrf

            <div>
                <label for="endpoint_name">Name</label>
                <input id="endpoint_name" name="name" type="text" value="{{ old('name') }}">
            </div>

            <div>
                <label for="endpoint_url">URL</label>
                <input id="endpoint_url" name="url" type="url" value="{{ old('url') }}">
            </div>

            <div>
                <label for="endpoint_status">Status</label>
                <select id="endpoint_status" name="status">
                    <option value="active">active</option>
                    <option value="disabled">disabled</option>
                </select>
            </div>

            <div>
                <label for="endpoint_description">Description</label>
                <textarea id="endpoint_description" name="description">{{ old('description') }}</textarea>
            </div>

            <button type="submit">Create endpoint</button>
        </form>

        @if ($project->webhookEndpoints->isNotEmpty())
            <ul>
                @foreach ($project->webhookEndpoints as $endpoint)
                    <li>
                        <strong>{{ $endpoint->name }}</strong>
                        <span>({{ $endpoint->status }})</span>
                        <p>{{ $endpoint->url }}</p>

                        <form method="POST" action="{{ route('projects.endpoints.toggle', [$project, $endpoint]) }}">
                            @csrf
                            <button type="submit">
                                {{ $endpoint->status === 'active' ? 'Disable' : 'Enable' }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p>No endpoints yet.</p>
        @endif
    </main>
</body>

</html>
