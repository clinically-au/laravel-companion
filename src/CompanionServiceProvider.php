<?php

declare(strict_types=1);

namespace Clinically\Companion;

use Clinically\Companion\Console\AgentCommand;
use Clinically\Companion\Console\AgentsCommand;
use Clinically\Companion\Console\InstallCommand;
use Clinically\Companion\Console\PairCommand;
use Clinically\Companion\Console\PruneAuditCommand;
use Clinically\Companion\Console\RevokeCommand;
use Clinically\Companion\Console\StatusCommand;
use Clinically\Companion\Http\Controllers\Api\CacheController;
use Clinically\Companion\Http\Controllers\Api\CapabilitiesController;
use Clinically\Companion\Http\Controllers\Api\CommandController;
use Clinically\Companion\Http\Controllers\Api\ConfigController;
use Clinically\Companion\Http\Controllers\Api\EnvironmentController;
use Clinically\Companion\Http\Controllers\Api\EventController;
use Clinically\Companion\Http\Controllers\Api\HorizonController;
use Clinically\Companion\Http\Controllers\Api\LogController;
use Clinically\Companion\Http\Controllers\Api\MigrationController;
use Clinically\Companion\Http\Controllers\Api\ModelController;
use Clinically\Companion\Http\Controllers\Api\ModelRecordController;
use Clinically\Companion\Http\Controllers\Api\PingController;
use Clinically\Companion\Http\Controllers\Api\PulseController;
use Clinically\Companion\Http\Controllers\Api\QueueController;
use Clinically\Companion\Http\Controllers\Api\RouteController;
use Clinically\Companion\Http\Controllers\Api\ScheduleController;
use Clinically\Companion\Http\Middleware\CompanionAuditMiddleware;
use Clinically\Companion\Http\Middleware\CompanionAuthMiddleware;
use Clinically\Companion\Http\Middleware\CompanionFeatureMiddleware;
use Clinically\Companion\Http\Middleware\CompanionScopeMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CompanionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/companion.php', 'companion');

        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerMiddleware();
        $this->registerRequestMacro();
        $this->registerGate();
        $this->registerRateLimiting();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerViews();
        $this->registerLivewireComponents();
        $this->registerSchedule();
    }

    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/companion.php' => config_path('companion.php'),
        ], 'companion-config');

        $this->publishes([
            __DIR__.'/../stubs/create_companion_agents_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_companion_agents_table.php'),
            __DIR__.'/../stubs/create_companion_audit_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_companion_audit_logs_table.php'),
        ], 'companion-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/companion'),
        ], 'companion-views');
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('companion.auth', CompanionAuthMiddleware::class);
        $router->aliasMiddleware('companion.scope', CompanionScopeMiddleware::class);
        $router->aliasMiddleware('companion.audit', CompanionAuditMiddleware::class);
        $router->aliasMiddleware('companion.feature', CompanionFeatureMiddleware::class);
    }

    private function registerRequestMacro(): void
    {
        Request::macro('companionAgent', function (): ?Models\CompanionAgent {
            /** @var Request $this */
            return $this->attributes->get('companion_agent');
        });
    }

    private function registerGate(): void
    {
        Gate::define('viewCompanion', function ($user = null) {
            if ($this->app->environment('local')) {
                return true;
            }

            return false;
        });
    }

    private function registerRateLimiting(): void
    {
        RateLimiter::for('companion', function (Request $request) {
            return Limit::perMinute(
                (int) config('companion.rate_limit.api', 120)
            )->by('companion:'.($request->ip() ?? 'unknown'));
        });

        RateLimiter::for('companion-sse', function (Request $request) {
            return Limit::perMinute(
                (int) config('companion.rate_limit.sse', 5)
            )->by('companion-sse:'.($request->ip() ?? 'unknown'));
        });
    }

    private function registerRoutes(): void
    {
        $features = $this->app->make(FeatureRegistry::class);
        $path = config('companion.path', 'companion');
        $middleware = array_merge(['api'], (array) config('companion.middleware', []), ['companion.auth', 'companion.audit']);

        $routeGroup = Route::prefix("{$path}/api")
            ->middleware($middleware);

        if ($domain = config('companion.domain')) {
            $routeGroup = $routeGroup->domain($domain);
        }

        $routeGroup->group(function () use ($features) {
            $this->registerHandshakeRoutes();
            $this->registerFeatureRoutes($features);
            $this->registerCustomFeatureRoutes($features);
        });

        if ($features->enabled('dashboard')) {
            $this->registerDashboardRoutes($path);
        }
    }

    private function registerHandshakeRoutes(): void
    {
        Route::get('/ping', PingController::class)
            ->name('companion.api.ping');

        Route::get('/capabilities', CapabilitiesController::class)
            ->name('companion.api.capabilities');
    }

    private function registerFeatureRoutes(FeatureRegistry $features): void
    {
        if ($features->enabled('environment')) {
            Route::get('/environment', EnvironmentController::class)
                ->middleware('companion.scope:environment:read')
                ->name('companion.api.environment');
        }

        if ($features->enabled('models')) {
            Route::get('/models', [ModelController::class, 'index'])
                ->middleware('companion.scope:models:read')
                ->name('companion.api.models.index');

            Route::get('/models/{model}', [ModelController::class, 'show'])
                ->middleware('companion.scope:models:read')
                ->name('companion.api.models.show');

            Route::get('/models/{model}/relationships', [ModelController::class, 'relationships'])
                ->middleware('companion.scope:models:read')
                ->name('companion.api.models.relationships');

            if ($features->enabled('models.browse')) {
                Route::get('/models/{model}/records', [ModelRecordController::class, 'index'])
                    ->middleware('companion.scope:models:browse')
                    ->name('companion.api.models.records.index');

                Route::get('/models/{model}/records/{id}', [ModelRecordController::class, 'show'])
                    ->middleware('companion.scope:models:browse')
                    ->name('companion.api.models.records.show');
            }
        }

        if ($features->enabled('routes')) {
            Route::get('/routes', RouteController::class)
                ->middleware('companion.scope:routes:read')
                ->name('companion.api.routes.index');
        }

        if ($features->enabled('commands')) {
            Route::get('/commands', [CommandController::class, 'index'])
                ->middleware('companion.scope:commands:list')
                ->name('companion.api.commands.index');

            Route::get('/commands/whitelisted', [CommandController::class, 'whitelisted'])
                ->middleware('companion.scope:commands:list')
                ->name('companion.api.commands.whitelisted');

            if ($features->enabled('commands.execute')) {
                Route::post('/commands/{command}/run', [CommandController::class, 'run'])
                    ->middleware('companion.scope:commands:execute')
                    ->name('companion.api.commands.run');
            }
        }

        if ($features->enabled('queues')) {
            Route::get('/queues', [QueueController::class, 'index'])
                ->middleware('companion.scope:queues:read')
                ->name('companion.api.queues.index');

            Route::get('/queues/failed', [QueueController::class, 'failed'])
                ->middleware('companion.scope:queues:read')
                ->name('companion.api.queues.failed.index');

            Route::get('/queues/failed/{id}', [QueueController::class, 'failedShow'])
                ->middleware('companion.scope:queues:read')
                ->name('companion.api.queues.failed.show');

            if ($features->enabled('queues.write')) {
                Route::post('/queues/failed/{id}/retry', [QueueController::class, 'retry'])
                    ->middleware('companion.scope:queues:write')
                    ->name('companion.api.queues.failed.retry');

                Route::post('/queues/failed/retry-all', [QueueController::class, 'retryAll'])
                    ->middleware('companion.scope:queues:write')
                    ->name('companion.api.queues.failed.retry-all');

                Route::delete('/queues/failed/{id}', [QueueController::class, 'destroy'])
                    ->middleware('companion.scope:queues:write')
                    ->name('companion.api.queues.failed.destroy');

                Route::delete('/queues/failed', [QueueController::class, 'flush'])
                    ->middleware('companion.scope:queues:write')
                    ->name('companion.api.queues.failed.flush');
            }
        }

        if ($features->enabled('cache')) {
            Route::get('/cache/info', [CacheController::class, 'info'])
                ->middleware('companion.scope:cache:read')
                ->name('companion.api.cache.info');

            if ($features->enabled('cache.read')) {
                Route::get('/cache/{key}', [CacheController::class, 'show'])
                    ->middleware('companion.scope:cache:read')
                    ->where('key', '.*')
                    ->name('companion.api.cache.show');
            }

            if ($features->enabled('cache.write')) {
                Route::delete('/cache/{key}', [CacheController::class, 'forget'])
                    ->middleware('companion.scope:cache:write')
                    ->where('key', '.*')
                    ->name('companion.api.cache.forget');

                Route::post('/cache/flush', [CacheController::class, 'flush'])
                    ->middleware('companion.scope:cache:write')
                    ->name('companion.api.cache.flush');
            }
        }

        if ($features->enabled('config')) {
            Route::get('/config', [ConfigController::class, 'index'])
                ->middleware('companion.scope:config:read')
                ->name('companion.api.config.index');

            Route::get('/config/{key}', [ConfigController::class, 'show'])
                ->middleware('companion.scope:config:read')
                ->where('key', '.*')
                ->name('companion.api.config.show');
        }

        if ($features->enabled('logs')) {
            Route::get('/logs', [LogController::class, 'index'])
                ->middleware('companion.scope:logs:read')
                ->name('companion.api.logs.index');

            Route::get('/logs/{file}', [LogController::class, 'show'])
                ->middleware('companion.scope:logs:read')
                ->name('companion.api.logs.show');

            if ($features->enabled('logs.stream')) {
                Route::get('/logs/{file}/stream', [LogController::class, 'stream'])
                    ->middleware(['companion.scope:logs:read', 'throttle:companion-sse'])
                    ->name('companion.api.logs.stream');
            }
        }

        if ($features->enabled('schedule')) {
            Route::get('/schedule', ScheduleController::class)
                ->middleware('companion.scope:schedule:read')
                ->name('companion.api.schedule.index');
        }

        if ($features->enabled('migrations')) {
            Route::get('/migrations', MigrationController::class)
                ->middleware('companion.scope:migrations:read')
                ->name('companion.api.migrations.index');
        }

        if ($features->enabled('events')) {
            Route::get('/events', EventController::class)
                ->middleware('companion.scope:events:read')
                ->name('companion.api.events.index');
        }

        if ($features->enabled('horizon')) {
            Route::get('/horizon/status', [HorizonController::class, 'status'])
                ->middleware('companion.scope:horizon:read')
                ->name('companion.api.horizon.status');

            Route::get('/horizon/metrics/jobs', [HorizonController::class, 'jobMetrics'])
                ->middleware('companion.scope:horizon:read')
                ->name('companion.api.horizon.metrics.jobs');

            Route::get('/horizon/recent-jobs', [HorizonController::class, 'recentJobs'])
                ->middleware('companion.scope:horizon:read')
                ->name('companion.api.horizon.recent-jobs');

            if ($features->enabled('horizon.write')) {
                Route::post('/horizon/pause', [HorizonController::class, 'pause'])
                    ->middleware('companion.scope:horizon:write')
                    ->name('companion.api.horizon.pause');

                Route::post('/horizon/continue', [HorizonController::class, 'continue'])
                    ->middleware('companion.scope:horizon:write')
                    ->name('companion.api.horizon.continue');

                Route::post('/horizon/terminate', [HorizonController::class, 'terminate'])
                    ->middleware('companion.scope:horizon:write')
                    ->name('companion.api.horizon.terminate');
            }
        }

        if ($features->enabled('pulse')) {
            Route::get('/pulse/servers', [PulseController::class, 'servers'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.servers');

            Route::get('/pulse/slow-queries', [PulseController::class, 'slowQueries'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.slow-queries');

            Route::get('/pulse/slow-requests', [PulseController::class, 'slowRequests'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.slow-requests');

            Route::get('/pulse/slow-jobs', [PulseController::class, 'slowJobs'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.slow-jobs');

            Route::get('/pulse/exceptions', [PulseController::class, 'exceptions'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.exceptions');

            Route::get('/pulse/usage', [PulseController::class, 'usage'])
                ->middleware('companion.scope:pulse:read')
                ->name('companion.api.pulse.usage');
        }
    }

    private function registerCustomFeatureRoutes(FeatureRegistry $features): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        foreach ($features->customFeatures() as $name => $registrar) {
            if ($features->enabled($name)) {
                $registrar($router);
            }
        }
    }

    private function registerDashboardRoutes(string $path): void
    {
        Route::prefix("{$path}/dashboard")
            ->middleware(['web', 'auth', 'can:viewCompanion'])
            ->group(function () {
                Route::get('/', [Http\Controllers\Dashboard\OverviewController::class, 'index'])
                    ->name('companion.dashboard.overview');

                Route::get('/agents', [Http\Controllers\Dashboard\AgentController::class, 'index'])
                    ->name('companion.dashboard.agents.index');

                Route::get('/agents/create', [Http\Controllers\Dashboard\AgentController::class, 'create'])
                    ->name('companion.dashboard.agents.create');

                Route::post('/agents', [Http\Controllers\Dashboard\AgentController::class, 'store'])
                    ->name('companion.dashboard.agents.store');

                Route::get('/agents/{agent}', [Http\Controllers\Dashboard\AgentController::class, 'show'])
                    ->name('companion.dashboard.agents.show');

                Route::delete('/agents/{agent}', [Http\Controllers\Dashboard\AgentController::class, 'destroy'])
                    ->name('companion.dashboard.agents.destroy');

                Route::get('/audit', [Http\Controllers\Dashboard\AuditController::class, 'index'])
                    ->name('companion.dashboard.audit.index');

                Route::get('/audit/{entry}', [Http\Controllers\Dashboard\AuditController::class, 'show'])
                    ->name('companion.dashboard.audit.show');

                Route::get('/features', [Http\Controllers\Dashboard\FeatureController::class, 'index'])
                    ->name('companion.dashboard.features.index');
            });

        // OpenAPI spec (public, no auth required)
        Route::prefix($path)->group(function () {
            Route::get('/api/docs/openapi.yaml', function () {
                $specPath = __DIR__.'/../resources/openapi.yaml';

                if (! is_file($specPath)) {
                    abort(404);
                }

                return response()->file($specPath, ['Content-Type' => 'text/yaml']);
            })->name('companion.api.docs.spec');

            Route::get('/api/docs', function () {
                $specUrl = route('companion.api.docs.spec');

                return response(<<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>Companion API Docs</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
                </head>
                <body>
                    <div id="swagger-ui"></div>
                    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                    <script>
                        SwaggerUIBundle({ url: "{$specUrl}", dom_id: '#swagger-ui', deepLinking: true });
                    </script>
                </body>
                </html>
                HTML);
            })->name('companion.api.docs');
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            AgentCommand::class,
            PairCommand::class,
            AgentsCommand::class,
            RevokeCommand::class,
            PruneAuditCommand::class,
            StatusCommand::class,
        ]);
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'companion');
    }

    private function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::component('companion-agent-list', Livewire\AgentList::class);
        \Livewire\Livewire::component('companion-agent-creator', Livewire\AgentCreator::class);
        \Livewire\Livewire::component('companion-agent-detail', Livewire\AgentDetail::class);
        \Livewire\Livewire::component('companion-qr-code', Livewire\QrCode::class);
        \Livewire\Livewire::component('companion-audit-log', Livewire\AuditLog::class);
        \Livewire\Livewire::component('companion-scope-picker', Livewire\ScopePicker::class);
        \Livewire\Livewire::component('companion-feature-status', Livewire\FeatureStatus::class);
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('companion.audit.enabled') && config('companion.audit.retention_days')) {
                $schedule->command('companion:prune-audit')->daily();
            }
        });
    }
}
