<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Middleware;

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanionAuditMiddleware
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

        /** @var CompanionAgent|null $agent */
        $agent = $request->attributes->get('companion_agent');

        if ($agent) {
            $this->auditService->log($agent, $request, $response->getStatusCode(), $durationMs);
        }

        return $response;
    }
}
