<div>
    <h3>Webhook endpoints</h3>

    @if ($project->webhookEndpoints->isNotEmpty())
        <ul>
            @foreach ($project->webhookEndpoints as $endpoint)
                <li>
                    <strong>{{ $endpoint->name }}</strong>
                    <span>({{ $endpoint->status }})</span>
                    <p>{{ $endpoint->url }}</p>
                </li>
            @endforeach
        </ul>
    @else
        <p>No webhook endpoints yet.</p>
    @endif
</div>
