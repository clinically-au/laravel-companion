<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Events\MutatingAction;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class CacheController extends Controller
{
    public function info(): JsonResponse
    {
        $driver = config('cache.default');
        $data = [
            'driver' => $driver,
            'store' => config("cache.stores.{$driver}.driver"),
        ];

        if ($data['store'] === 'redis') {
            try {
                /** @var RedisStore $store */
                $store = Cache::store()->getStore();
                $connection = $store->getRedis()->connection($store->getRedis()->connections()[0] ?? 'default');
                $info = $connection->info();
                $data['redis'] = [
                    'connected_clients' => $info['Clients']['connected_clients'] ?? null,
                    'used_memory_human' => $info['Memory']['used_memory_human'] ?? null,
                    'db_size' => $connection->dbsize(),
                ];
            } catch (\Throwable) {
                // Redis info not available
            }
        }

        return $this->respond($data);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        if (! $this->isKeyAllowed($key)) {
            return $this->error('Cache key not permitted.', 'key_not_allowed', 403);
        }

        if (! Cache::has($key)) {
            return $this->error('Cache key not found.', 'not_found', 404);
        }

        $value = Cache::get($key);

        return $this->respond([
            'key' => $key,
            'value' => $this->serialiseValue($value),
            'type' => get_debug_type($value),
        ]);
    }

    public function forget(Request $request, string $key): JsonResponse
    {
        if (! $this->isKeyAllowed($key)) {
            return $this->error('Cache key not permitted.', 'key_not_allowed', 403);
        }

        MutatingAction::dispatch($this->agent($request), 'cache.forget', ['key' => $key]);

        Cache::forget($key);

        return $this->respond(['message' => "Cache key '{$key}' forgotten."]);
    }

    public function flush(Request $request): JsonResponse
    {
        if (! $request->boolean('confirm')) {
            return $this->error('Confirmation required. Send { "confirm": true }.', 'confirmation_required', 422);
        }

        MutatingAction::dispatch($this->agent($request), 'cache.flush', []);

        Cache::flush();

        return $this->respond(['message' => 'Cache flushed.']);
    }

    /**
     * Check if a cache key is allowed based on configured prefix allowlist.
     * If no prefixes are configured, all keys are allowed.
     */
    private function isKeyAllowed(string $key): bool
    {
        /** @var list<string> $allowedPrefixes */
        $allowedPrefixes = (array) config('companion.cache.allowed_prefixes', []);

        if ($allowedPrefixes === []) {
            return true;
        }

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function serialiseValue(mixed $value): mixed
    {
        if (is_scalar($value) || is_null($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return '['.get_debug_type($value).']';
    }
}
