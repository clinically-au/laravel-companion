<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Middleware;

use Clinically\Companion\FeatureRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanionFeatureMiddleware
{
    public function __construct(
        private readonly FeatureRegistry $features,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->features->enabled($feature)) {
            return response()->json([
                'error' => [
                    'code' => 'feature_disabled',
                    'message' => 'This feature is not available.',
                ],
            ], 404);
        }

        return $next($request);
    }
}
