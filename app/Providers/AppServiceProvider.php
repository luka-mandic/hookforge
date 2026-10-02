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
use App\Services\DeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $this->app->bind(DeliveryService::class, function ($app): DeliveryService {
            return new DeliveryService(
                $app->make(DeliveryPublisher::class),
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
            fn(): ?Password => app()->isProduction()
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
