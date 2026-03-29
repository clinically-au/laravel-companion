<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Build a standard success response with the companion envelope.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $extraMeta
     */
    protected function respond(array $data, int $status = 200, array $extraMeta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge($this->baseMeta(), $extraMeta),
        ], $status);
    }

    /**
     * Build a paginated response.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $pagination
     */
    protected function paginated(array $data, array $pagination): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge($this->baseMeta(), ['pagination' => $pagination]),
        ]);
    }

    /**
     * Build an error response.
     */
    protected function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => $this->baseMeta(),
        ], $status);
    }

    /**
     * Get the authenticated companion agent from the request.
     */
    protected function agent(Request $request): CompanionAgent
    {
        /** @var CompanionAgent */
        return $request->attributes->get('companion_agent');
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMeta(): array
    {
        return [
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'companion_version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
