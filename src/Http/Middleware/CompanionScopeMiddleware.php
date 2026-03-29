<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Middleware;

use Clinically\Companion\Models\CompanionAgent;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanionScopeMiddleware
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var CompanionAgent|null $agent */
        $agent = $request->attributes->get('companion_agent');

        if (! $agent) {
            return $this->error('Authentication required.', 'auth_required', 401);
        }

        if (! $agent->hasScope($scope)) {
            return response()->json([
                'error' => [
                    'code' => 'scope_denied',
                    'message' => "Agent lacks required scope: {$scope}",
                ],
            ], 403);
        }

        return $next($request);
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
