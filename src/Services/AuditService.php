<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Http\Request;

final class AuditService
{
    /**
     * Log an API request to the audit log.
     */
    public function log(
        CompanionAgent $agent,
        Request $request,
        int $responseCode,
        int $durationMs,
    ): void {
        if (! config('companion.audit.enabled')) {
            return;
        }

        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if (! $isWrite && ! config('companion.audit.log_reads')) {
            return;
        }

        CompanionAuditLog::create([
            'agent_id' => $agent->id,
            'action' => $this->resolveAction($request),
            'method' => $request->method(),
            'path' => $request->path(),
            'payload' => $isWrite ? $this->sanitisePayload($request->all()) : null,
            'response_code' => $responseCode,
            'ip' => $request->ip() ?? 'unknown',
            'user_agent' => $request->userAgent(),
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    /**
     * Resolve a human-readable action from the route name.
     */
    private function resolveAction(Request $request): string
    {
        $route = $request->route();

        if ($route && $route->getName()) {
            // Strip 'companion.api.' prefix: companion.api.models.index -> models.index
            $name = $route->getName();

            return str_starts_with($name, 'companion.api.')
                ? substr($name, 14)
                : $name;
        }

        return $request->method().' '.$request->path();
    }

    /**
     * Strip sensitive values from the request payload before logging.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitisePayload(array $payload): array
    {
        $redactKeys = ['password', 'secret', 'token', 'key', 'authorization'];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitisePayload($value);
            } elseif (is_string($key)) {
                foreach ($redactKeys as $pattern) {
                    if (stripos($key, $pattern) !== false) {
                        $payload[$key] = '********';
                        break;
                    }
                }
            }
        }

        return $payload;
    }
}
