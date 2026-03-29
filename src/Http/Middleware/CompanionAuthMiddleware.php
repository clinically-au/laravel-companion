<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Middleware;

use Clinically\Companion\Events\AgentAuthenticated;
use Clinically\Companion\Events\AgentAuthFailed;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\TokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanionAuthMiddleware
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Enforce HTTPS in production
        if (! app()->environment('local', 'testing') && ! $request->isSecure()) {
            return $this->error('HTTPS is required.', 'https_required', 403);
        }

        $token = $request->bearerToken();

        if (! $token) {
            return $this->error('Authentication token required.', 'token_missing', 401);
        }

        $agent = $this->tokenService->findByToken($token);

        if (! $agent) {
            AgentAuthFailed::dispatch(
                substr($token, 0, 16),
                'invalid_token',
                $request,
            );

            return $this->error('Invalid authentication token.', 'token_invalid', 401);
        }

        if ($agent->isRevoked()) {
            AgentAuthFailed::dispatch(
                $agent->token_prefix,
                'token_revoked',
                $request,
            );

            return $this->error('Token has been revoked.', 'token_revoked', 401);
        }

        if ($agent->isExpired()) {
            AgentAuthFailed::dispatch(
                $agent->token_prefix,
                'token_expired',
                $request,
            );

            return $this->error('Token has expired.', 'token_expired', 401);
        }

        if (! $agent->isIpAllowed($request->ip() ?? '')) {
            AgentAuthFailed::dispatch(
                $agent->token_prefix,
                'ip_not_allowed',
                $request,
            );

            return $this->error('IP address not allowed.', 'ip_not_allowed', 403);
        }

        // Attach agent to request
        $request->attributes->set('companion_agent', $agent);

        // Update last seen (throttled to once per minute)
        $this->updateLastSeen($agent, $request);

        AgentAuthenticated::dispatch($agent, $request);

        return $next($request);
    }

    private function updateLastSeen(CompanionAgent $agent, Request $request): void
    {
        if ($agent->last_seen_at !== null && $agent->last_seen_at->diffInSeconds(now()) < 60) {
            return;
        }

        $agent->update([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
        ]);
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
