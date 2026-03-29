<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PulseController extends Controller
{
    public function servers(): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) {
            return $query->where('type', 'server')
                ->orderByDesc('period')
                ->limit(50)
                ->get();
        });
    }

    public function slowQueries(Request $request): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) use ($request) {
            return $query->where('type', 'slow_query')
                ->orderByDesc('value')
                ->limit((int) $request->query('limit', '20'))
                ->get();
        });
    }

    public function slowRequests(Request $request): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) use ($request) {
            return $query->where('type', 'slow_request')
                ->orderByDesc('value')
                ->limit((int) $request->query('limit', '20'))
                ->get();
        });
    }

    public function slowJobs(Request $request): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) use ($request) {
            return $query->where('type', 'slow_job')
                ->orderByDesc('value')
                ->limit((int) $request->query('limit', '20'))
                ->get();
        });
    }

    public function exceptions(): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) {
            return $query->where('type', 'exception')
                ->orderByDesc('count')
                ->limit(50)
                ->get();
        });
    }

    public function usage(): JsonResponse
    {
        return $this->queryPulse('pulse_aggregates', function ($query) {
            return $query->where('type', 'user_request')
                ->orderByDesc('count')
                ->limit(20)
                ->get();
        });
    }

    private function queryPulse(string $table, callable $callback): JsonResponse
    {
        if (! class_exists('Laravel\Pulse\Pulse')) {
            return $this->error('Pulse is not installed.', 'not_available', 404);
        }

        try {
            $connection = config('pulse.storage.database.connection') ?? config('database.default');
            $query = DB::connection($connection)->table($table);
            $results = $callback($query);

            return $this->respond($results->toArray());
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to query Pulse data: '.$e->getMessage(),
                'pulse_query_error',
                500,
            );
        }
    }
}
