<div>
    <h2>Projects</h2>

    <ul>
        @forelse ($projects as $project)
            <li>
                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                <span>({{ $project->status }})</span>
            </li>
        @empty
            <li>No projects available.</li>
        @endforelse
    </ul>
</div>
