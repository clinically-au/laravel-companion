<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class MigrationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $ran = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => [
                'migration' => $row->migration,
                'batch' => $row->batch,
                'ran' => true,
            ]);

        // Group by batch for the response
        $grouped = $ran->groupBy('batch')
            ->map(fn ($migrations, $batch) => [
                'batch' => $batch,
                'migrations' => $migrations->pluck('migration')->values()->all(),
            ])
            ->values()
            ->all();

        return $this->respond([
            'batches' => $grouped,
            'total' => $ran->count(),
        ]);
    }
}
