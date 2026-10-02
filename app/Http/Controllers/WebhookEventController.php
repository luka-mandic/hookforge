<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebhookEventRequest;
use App\Models\Project;
use App\Services\WebhookEventService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class WebhookEventController extends Controller
{
    use AuthorizesRequests;

    public function store(Project $project, StoreWebhookEventRequest $request, WebhookEventService $service): JsonResponse
    {
        $this->authorize('view', $project);

        ['event' => $event, 'created' => $created] = $service->publish($project, $request->validated());

        return response()->json([
            'id' => $event->getKey(),
            'type' => $event->event_type,
            'data' => $event->payload,
        ], $created ? 201 : 200);
    }
}
