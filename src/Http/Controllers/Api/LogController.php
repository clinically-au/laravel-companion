<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Services\LogParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LogController extends Controller
{
    public function __construct(
        private readonly LogParserService $logParser,
    ) {}

    public function index(): JsonResponse
    {
        $path = (string) config('companion.logs.path', storage_path('logs'));

        if (! is_dir($path)) {
            return $this->respond([]);
        }

        $files = collect(scandir($path) ?: [])
            ->filter(fn (string $file) => str_ends_with($file, '.log'))
            ->map(function (string $file) use ($path) {
                $fullPath = "{$path}/{$file}";

                return [
                    'name' => $file,
                    'size' => filesize($fullPath),
                    'size_human' => $this->humanFileSize(filesize($fullPath) ?: 0),
                    'last_modified' => date('c', filemtime($fullPath) ?: 0),
                ];
            })
            ->sortByDesc('last_modified')
            ->values()
            ->all();

        return $this->respond($files);
    }

    public function show(Request $request, string $file): JsonResponse
    {
        $filePath = $this->resolveLogPath($file);

        if ($filePath === null) {
            return $this->error('Log file not found.', 'not_found', 404);
        }

        $maxSize = (int) config('companion.logs.max_file_size_mb', 50) * 1024 * 1024;
        if (filesize($filePath) > $maxSize) {
            return $this->error('Log file too large. Use the stream endpoint.', 'file_too_large', 413);
        }

        $tailLines = (int) config('companion.logs.tail_lines', 500);
        $content = $this->logParser->tailFile($filePath, $tailLines);

        $entries = $this->logParser->parse(
            $content,
            $request->query('level') ? (string) $request->query('level') : null,
            $request->query('search') ? (string) $request->query('search') : null,
        );

        // Reverse chronological
        $entries = array_reverse($entries);

        // Paginate
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min((int) $request->query('per_page', '50'), 200);
        $total = count($entries);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return $this->paginated($slice, [
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function stream(Request $request, string $file): StreamedResponse
    {
        $filePath = $this->resolveLogPath($file);

        if ($filePath === null) {
            return new StreamedResponse(function () {
                echo "event: error\ndata: ".json_encode(['message' => 'Log file not found.'])."\n\n";
            }, 404, $this->sseHeaders());
        }

        $levelFilter = $request->query('level') ? (string) $request->query('level') : null;

        return new StreamedResponse(function () use ($filePath, $levelFilter) {
            $lastSize = filesize($filePath) ?: 0;
            $heartbeat = 0;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                clearstatcache(true, $filePath);
                $currentSize = filesize($filePath) ?: 0;

                if ($currentSize > $lastSize) {
                    $handle = fopen($filePath, 'r');

                    if ($handle) {
                        fseek($handle, $lastSize);
                        $newContent = fread($handle, $currentSize - $lastSize);
                        fclose($handle);

                        if ($newContent) {
                            $entries = $this->logParser->parse($newContent, $levelFilter);

                            foreach ($entries as $entry) {
                                echo 'data: '.json_encode($entry)."\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }

                    $lastSize = $currentSize;
                } elseif ($currentSize < $lastSize) {
                    // File was truncated/rotated
                    $lastSize = 0;
                }

                // Heartbeat every 15 seconds
                if (++$heartbeat >= 15) {
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                    $heartbeat = 0;
                }

                sleep(1);
            }
        }, 200, $this->sseHeaders());
    }

    private function resolveLogPath(string $file): ?string
    {
        // Prevent directory traversal
        $file = basename($file);

        if (! str_ends_with($file, '.log')) {
            return null;
        }

        $path = config('companion.logs.path', storage_path('logs')).'/'.$file;

        return is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * @return array<string, string>
     */
    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
