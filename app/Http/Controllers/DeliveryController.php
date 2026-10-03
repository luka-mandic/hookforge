<?php

namespace App\Http\Controllers;

use App\Models\WebhookDelivery;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function show(WebhookDelivery $delivery): View
    {
        $this->authorize('view', $delivery->project);

        $delivery->load(['project', 'endpoint', 'event', 'attempts']);

        return view('deliveries.show', compact('delivery'));
    }
}
