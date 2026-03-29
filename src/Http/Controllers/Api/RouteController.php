<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

final class RouteController extends Controller
{
    public function __construct(
        private readonly Router $router,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $routes = collect($this->router->getRoutes()->getRoutes())
            ->map(fn (Route $route) => $this->formatRoute($route))
            ->values();

        // Apply filters
        if ($method = $request->query('method')) {
            $routes = $routes->filter(fn (array $r) => in_array(strtoupper((string) $method), $r['methods'], true));
        }

        if ($name = $request->query('name')) {
            $routes = $routes->filter(fn (array $r) => $r['name'] && str_starts_with($r['name'], (string) $name));
        }

        if ($uri = $request->query('uri')) {
            $routes = $routes->filter(fn (array $r) => str_contains($r['uri'], (string) $uri));
        }

        if ($middleware = $request->query('middleware')) {
            $routes = $routes->filter(fn (array $r) => in_array((string) $middleware, $r['middleware'], true));
        }

        if ($controller = $request->query('controller')) {
            $routes = $routes->filter(fn (array $r) => $r['action'] && str_contains($r['action'], (string) $controller));
        }

        return $this->respond($routes->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoute(Route $route): array
    {
        $action = $route->getActionName();

        return [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $action === 'Closure' ? 'Closure' : $action,
            'middleware' => $this->extractMiddleware($route),
            'domain' => $route->getDomain(),
            'parameters' => $route->parameterNames(),
        ];
    }

    /**
     * @return list<string>
     */
    private function extractMiddleware(Route $route): array
    {
        return array_values(array_unique(
            $route->gatherMiddleware()
        ));
    }
}
