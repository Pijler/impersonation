<?php

namespace Pijler\Impersonation;

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Pijler\Impersonation\Http\Controllers\ImpersonationController;
use Pijler\Impersonation\Http\Middleware\HandleImpersonation;
use Pijler\Impersonation\Http\Middleware\ProtectFromImpersonation;
use Pijler\Impersonation\Listeners\EndImpersonationOnLogout;
use Pijler\Impersonation\Listeners\RecordImpersonation;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootConfig();

        $this->bootViews();

        $this->bootRoutes();

        $this->bootMigrations();

        $this->bootMiddleware();

        $this->bootEventListeners();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/impersonation.php', 'impersonation');
    }

    /**
     * Publish the package config file.
     */
    private function bootConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/impersonation.php' => config_path('impersonation.php'),
        ], 'impersonation-config');
    }

    /**
     * Register and publish the package views.
     */
    private function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'impersonation');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/impersonation'),
        ], 'impersonation-views');
    }

    /**
     * Register the macro that adds the package routes to the application.
     */
    private function bootRoutes(): void
    {
        Route::macro('impersonation', function (string $prefix = 'impersonation') {
            Route::prefix($prefix)->name('impersonation.')->group(function () {
                Route::delete('/', [ImpersonationController::class, 'stop'])->name('stop');

                Route::post('{target}', [ImpersonationController::class, 'start'])->name('start');
            });
        });
    }

    /**
     * Publish the package migrations to the application's migrations directory.
     */
    private function bootMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'impersonation-migrations');
    }

    /**
     * Register the package middleware in the Laravel router.
     *
     * The priority keeps the switch to the target after "auth" and "auth.session",
     * which must see the actor, and before the route bindings and "can", which must
     * see the target, wherever the application lists the middleware.
     */
    private function bootMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('impersonation', HandleImpersonation::class);

        $router->aliasMiddleware('impersonation.protect', ProtectFromImpersonation::class);

        $this->callAfterResolving(KernelContract::class, function (KernelContract $kernel) {
            if ($kernel instanceof Kernel) {
                $kernel->addToMiddlewarePriorityAfter(AuthenticatesSessions::class, HandleImpersonation::class);
            }
        });
    }

    /**
     * Register the package event listeners.
     */
    private function bootEventListeners(): void
    {
        Event::subscribe(RecordImpersonation::class);

        Event::listen([Logout::class, CurrentDeviceLogout::class], EndImpersonationOnLogout::class);
    }
}
