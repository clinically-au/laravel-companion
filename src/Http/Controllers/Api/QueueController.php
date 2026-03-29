<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Events\MutatingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class QueueController extends Controller
{
    public function index(): JsonResponse
    {
        $connection = config('queue.default');
        $queues = ['default'];

        $sizes = [];
        foreach ($queues as $queue) {
            $sizes[$queue] = Queue::size($queue);
        }

        return $this->respond([
            'connection' => $connection,
            'driver' => config("queue.connections.{$connection}.driver"),
            'queues' => $sizes,
        ]);
    }

    public function failed(Request $request): JsonResponse
    {
        $query = DB::table('failed_jobs')
            ->orderByDesc('failed_at');

        if ($queueFilter = $request->query('queue')) {
            $query->where('queue', $queueFilter);
        }

        $perPage = min((int) $request->query('per_page', '25'), 100);
        $paginator = $query->paginate($perPage);

        /** @var array<int, \stdClass> $failedItems */
        $failedItems = $paginator->items();
        $jobs = collect($failedItems)
            ->map(fn (\stdClass $job) => [
                'id' => $job->id,
                'uuid' => $job->uuid ?? null,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'exception' => Str::limit($job->exception, 500),
                'failed_at' => $job->failed_at,
            ])
            ->all();

        return $this->paginated($jobs, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function failedShow(int $id): JsonResponse
    {
        /** @var object|null $job */
        $job = DB::table('failed_jobs')->find($id);

        if (! $job) {
            return $this->error('Failed job not found.', 'not_found', 404);
        }

        return $this->respond([
            'id' => $job->id,
            'uuid' => $job->uuid ?? null,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'payload' => json_decode($job->payload, true),
            'exception' => $job->exception,
            'failed_at' => $job->failed_at,
        ]);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        /** @var object|null $job */
        $job = DB::table('failed_jobs')->find($id);

        if (! $job) {
            return $this->error('Failed job not found.', 'not_found', 404);
        }

        MutatingAction::dispatch($this->agent($request), 'queues.failed.retry', ['id' => $id]);

        Artisan::call('queue:retry', ['id' => [$job->uuid ?? $id]]);

        return $this->respond(['message' => 'Job queued for retry.']);
    }

    public function retryAll(Request $request): JsonResponse
    {
        MutatingAction::dispatch($this->agent($request), 'queues.failed.retry-all', []);

        $count = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);

        return $this->respond([
            'message' => 'All failed jobs queued for retry.',
            'count' => $count,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var object|null $job */
        $job = DB::table('failed_jobs')->find($id);

        if (! $job) {
            return $this->error('Failed job not found.', 'not_found', 404);
        }

        MutatingAction::dispatch($this->agent($request), 'queues.failed.destroy', ['id' => $id]);

        Artisan::call('queue:forget', ['id' => $job->uuid ?? $id]);

        return $this->respond(['message' => 'Failed job deleted.']);
    }

    public function flush(Request $request): JsonResponse
    {
        if (! $request->boolean('confirm')) {
            return $this->error('Confirmation required. Send { "confirm": true }.', 'confirmation_required', 422);
        }

        MutatingAction::dispatch($this->agent($request), 'queues.failed.flush', []);

        Artisan::call('queue:flush');

        return $this->respond(['message' => 'All failed jobs flushed.']);
    }
}
