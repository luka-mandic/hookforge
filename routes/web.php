<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WebhookEndpointController;
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
});

Route::view('/', 'welcome')->name('home');
