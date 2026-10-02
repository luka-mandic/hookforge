<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit project</title>
</head>

<body>
    <main>
        <h1>Edit project</h1>

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf
            @method('PUT')

            <div>
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}">
            </div>

            <div>
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description', $project->description) }}</textarea>
            </div>

            <button type="submit">Update project</button>
        </form>
    </main>
</body>

</html>
