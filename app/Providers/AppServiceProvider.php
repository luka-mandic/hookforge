<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\WebhookEndpoint;
use App\Policies\ProjectPolicy;
use App\Policies\WebhookEndpointPolicy;
use App\RabbitMq\AmqpMessageTransport;
use App\RabbitMq\DeliveryPublisher;
use App\RabbitMq\EventPublisher;
use App\RabbitMq\RabbitMqConfig;
use App\Services\DefaultRetryPolicy;
use App\Services\DeliveryService;
use App\Services\RetryPolicy;
use App\Services\WebhookDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EventPublisher::class, function (): EventPublisher {
            return new EventPublisher(
                RabbitMqConfig::fromEnvironment(),
                new AmqpMessageTransport,
            );
        });

        $this->app->singleton(DeliveryPublisher::class, function (): DeliveryPublisher {
            return new DeliveryPublisher(
                RabbitMqConfig::fromEnvironment(),
                new AmqpMessageTransport,
            );
        });

        $this->app->bind(DeliveryService::class, function (Application $app): DeliveryService {
            return new DeliveryService(
                $app->make(DeliveryPublisher::class),
            );
        });

        $this->app->singleton(RetryPolicy::class, function (): RetryPolicy {
            return new DefaultRetryPolicy(
                maxAttempts: 5,
                initialDelayMs: 1000,
                maxDelayMs: 30000,
            );
        });

        $this->app->singleton(WebhookDeliveryService::class, function (Application $app): WebhookDeliveryService {
            return new WebhookDeliveryService(
                retryPolicy: $app->make(RetryPolicy::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);
        $this->configureRateLimiting();
    }

    /**
     * Rate limit event publishing per authenticated identity and project.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('event-publishing', function (Request $request): Limit {
            $project = $request->route('project');
            $projectKey = $project instanceof Project ? $project->id : (is_string($project) ? $project : '');
            $identity = $request->user()->id ?? $request->ip() ?? 'unknown';

            return Limit::perSecond(
                config()->integer('rate_limiting.event_publishing.max_attempts'),
                config()->integer('rate_limiting.event_publishing.decay_seconds'),
            )->by("{$projectKey}|{$identity}");
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
