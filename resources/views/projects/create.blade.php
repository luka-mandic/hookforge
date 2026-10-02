<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create project</title>
</head>

<body>
    <main>
        <h1>Create project</h1>

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('projects.store') }}">
            @csrf

            <div>
                <label for="name">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}">
            </div>

            <div>
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description') }}</textarea>
            </div>

            <button type="submit">Create project</button>
        </form>
    </main>
</body>

</html>
