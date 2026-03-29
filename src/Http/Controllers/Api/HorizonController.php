<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Events\MutatingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

final class HorizonController extends Controller
{
    public function status(): JsonResponse
    {
        if (! class_exists('Laravel\Horizon\Contracts\MasterSupervisorRepository')) {
            return $this->error('Horizon is not installed.', 'not_available', 404);
        }

        $masters = app('Laravel\Horizon\Contracts\MasterSupervisorRepository');
        $supervisors = $masters->all();
        $status = empty($supervisors) ? 'inactive' : 'running';

        if (! empty($supervisors)) {
            $supervisorRepo = app('Laravel\Horizon\Contracts\SupervisorRepository');
            /** @var array<int, object> $allSupervisors */
            $allSupervisors = $supervisorRepo->all();
            $allPaused = collect($allSupervisors)->every(fn (object $s) => $s->status === 'paused');
            if ($allPaused) {
                $status = 'paused';
            }
        }

        return $this->respond([
            'status' => $status,
            'supervisors' => count($supervisors),
        ]);
    }

    public function jobMetrics(): JsonResponse
    {
        if (! class_exists('Laravel\Horizon\Contracts\MetricsRepository')) {
            return $this->error('Horizon is not installed.', 'not_available', 404);
        }

        $metrics = app('Laravel\Horizon\Contracts\MetricsRepository');

        return $this->respond([
            'jobs' => $metrics->measuredJobs(),
            'snapshots' => $metrics->snapshotsForJob('*'),
        ]);
    }

    public function recentJobs(Request $request): JsonResponse
    {
        if (! class_exists('Laravel\Horizon\Contracts\JobRepository')) {
            return $this->error('Horizon is not installed.', 'not_available', 404);
        }

        $jobs = app('Laravel\Horizon\Contracts\JobRepository');
        /** @var array<int, mixed> $recent */
        $recent = $jobs->getRecent();

        return $this->respond(
            collect($recent)->take(50)->values()->all()
        );
    }

    public function pause(Request $request): JsonResponse
    {
        MutatingAction::dispatch($this->agent($request), 'horizon.pause', []);
        Artisan::call('horizon:pause');

        return $this->respond(['message' => 'Horizon paused.']);
    }

    public function continue(Request $request): JsonResponse
    {
        MutatingAction::dispatch($this->agent($request), 'horizon.continue', []);
        Artisan::call('horizon:continue');

        return $this->respond(['message' => 'Horizon resumed.']);
    }

    public function terminate(Request $request): JsonResponse
    {
        MutatingAction::dispatch($this->agent($request), 'horizon.terminate', []);
        Artisan::call('horizon:terminate');

        return $this->respond(['message' => 'Horizon terminated.']);
    }
}
