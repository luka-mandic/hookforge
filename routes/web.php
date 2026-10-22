<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDeliveryController;
use App\Http\Controllers\ProjectEventController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WebhookEventController;
use App\Http\Controllers\WebhookSimulatorController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/login/test-user', [AuthController::class, 'loginAsTestUser'])->name('login.test-user');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::resource('projects', ProjectController::class);

    Route::get('/projects/{project}/events', [ProjectEventController::class, 'index'])
        ->name('projects.events.index');

    Route::get('/projects/{project}/deliveries', [ProjectDeliveryController::class, 'index'])
        ->name('projects.deliveries.index');

    Route::get('/deliveries', [DeliveryController::class, 'index'])
        ->name('deliveries.index');

    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])
        ->name('deliveries.show');

    Route::post('/projects/{project}/endpoints/{endpoint}/toggle', [WebhookEndpointController::class, 'toggle'])
        ->name('projects.endpoints.toggle');

    Route::post('/projects/{project}/test-endpoints', [WebhookSimulatorController::class, 'store'])
        ->name('projects.test-endpoints.store');

    Route::match(['GET', 'POST'], '/projects/{project}/test-endpoints/{token}', [WebhookSimulatorController::class, 'handle'])
        ->name('projects.test-endpoints.handle');

    Route::resource('projects.endpoints', WebhookEndpointController::class)
        ->except(['index', 'show'])
        ->shallow();

    Route::prefix('api/v1')->group(function () {
        Route::post('/projects/{project}/events', [WebhookEventController::class, 'store'])
            ->middleware('throttle:event-publishing')
            ->name('api.v1.projects.events.store');
    });
});
