<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

final class EnvironmentController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return $this->respond([
            'app_name' => config('app.name'),
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'drivers' => [
                'database' => config('database.default'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'mail' => config('mail.default'),
                'session' => config('session.driver'),
            ],
        ]);
    }
}
