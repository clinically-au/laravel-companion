<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Services\ConfigRedactionService;
use Illuminate\Http\JsonResponse;

final class ConfigController extends Controller
{
    public function __construct(
        private readonly ConfigRedactionService $redaction,
    ) {}

    public function index(): JsonResponse
    {
        /** @var array<string, mixed> $config */
        $config = config()->all();

        return $this->respond($this->redaction->redact($config));
    }

    public function show(string $key): JsonResponse
    {
        $value = config($key);

        if ($value === null && ! str_contains($key, '.')) {
            return $this->error('Config key not found.', 'not_found', 404);
        }

        $redacted = $this->redaction->redactValue($key, $value);

        return $this->respond([
            'key' => $key,
            'value' => $redacted,
        ]);
    }
}
