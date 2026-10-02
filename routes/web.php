<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WebhookEventController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::view('/login', 'auth.login')->name('login');

Route::middleware('auth')->group(function () {
    Route::resource('projects', ProjectController::class);

    Route::post('/projects/{project}/endpoints/{endpoint}/toggle', [WebhookEndpointController::class, 'toggle'])
        ->name('projects.endpoints.toggle');

    Route::resource('projects.endpoints', WebhookEndpointController::class)
        ->except(['index', 'show'])
        ->shallow();

    Route::prefix('api/v1')->group(function () {
        Route::post('/projects/{project}/events', [WebhookEventController::class, 'store'])
            ->name('api.v1.projects.events.store');
    });
});
