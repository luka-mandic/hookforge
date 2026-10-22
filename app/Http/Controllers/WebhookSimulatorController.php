<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookSimulatorController extends Controller
{
    use AuthorizesRequests;

    public function store(Project $project, Request $request): RedirectResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'response_status' => ['required', Rule::in(['200', '201', '400', '401', '404', '429', '500', 'timeout'])],
            'delay_ms' => ['nullable', 'integer', 'min:0', 'max:1500'],
        ]);

        $status = $request->string('response_status')->toString();
        $delayMs = $request->integer('delay_ms');
        $token = Str::random(32);

        $endpoint = WebhookEndpoint::query()->create([
            'project_id' => $project->id,
            'name' => 'Test endpoint · '.$status,
            'url' => route('projects.test-endpoints.handle', ['project' => $project, 'token' => $token]),
            'secret' => $token,
            'description' => $status === 'timeout'
                ? 'Simulated timeout endpoint. It intentionally delays before returning a timeout-style response.'
                : 'Simulated status: '.$status.' (delay: '.$delayMs.'ms)',
            'status' => 'active',
        ]);

        Cache::put(
            $this->cacheKey($project, $token),
            [
                'response_status' => $status,
                'delay_ms' => $delayMs,
                'endpoint_id' => $endpoint->id,
            ],
            now()->addDay(),
        );

        return redirect()->route('projects.show', $project)
            ->with('status', 'Test endpoint created successfully.');
    }

    public function handle(Project $project, string $token): JsonResponse
    {
        $endpoint = WebhookEndpoint::query()
            ->where('project_id', $project->id)
            ->where('secret', $token)
            ->firstOrFail();

        $config = Cache::get($this->cacheKey($project, $token));
        $config = is_array($config) ? $config : [];
        $responseStatus = is_string($config['response_status'] ?? null) ? $config['response_status'] : '200';
        $delayMs = min(is_int($config['delay_ms'] ?? null) ? $config['delay_ms'] : 0, 1500);

        if ($responseStatus === 'timeout') {
            usleep(min(2500000, max($delayMs, 2000000)));

            return response()->json([
                'ok' => false,
                'message' => 'Simulated timeout',
            ], 504);
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $httpStatus = (int) $responseStatus;

        return response()->json([
            'ok' => $httpStatus >= 200 && $httpStatus < 300,
            'message' => 'Simulated webhook response',
            'status' => $httpStatus,
            'endpoint' => $endpoint->name,
        ], $httpStatus);
    }

    protected function cacheKey(Project $project, string $token): string
    {
        return 'webhook-simulator:'.$project->id.':'.$token;
    }
}
