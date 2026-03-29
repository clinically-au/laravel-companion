<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

final class LogParserService
{
    /**
     * Standard Laravel log line pattern.
     */
    private const LOG_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?\d*[+-]?\d*:?\d*)\]\s+(\w+)\.(\w+):\s+(.*)/s';

    /**
     * Parse a log file into structured entries.
     *
     * @return list<array{level: string, datetime: string, channel: string, message: string, context: string|null, stack_trace: string|null}>
     */
    public function parse(string $content, ?string $levelFilter = null, ?string $search = null): array
    {
        $lines = explode("\n", $content);
        $entries = [];
        $currentEntry = null;

        foreach ($lines as $line) {
            if (preg_match(self::LOG_PATTERN, $line, $matches)) {
                // Save previous entry
                if ($currentEntry !== null) {
                    $entries[] = $this->finaliseEntry($currentEntry);
                }

                $currentEntry = [
                    'datetime' => $matches[1],
                    'channel' => $matches[2],
                    'level' => strtolower($matches[3]),
                    'message' => $matches[4],
                    'extra_lines' => [],
                ];
            } elseif ($currentEntry !== null && trim($line) !== '') {
                $currentEntry['extra_lines'][] = $line;
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null) {
            $entries[] = $this->finaliseEntry($currentEntry);
        }

        // Apply filters
        if ($levelFilter !== null) {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => $entry['level'] === strtolower($levelFilter),
            ));
        }

        if ($search !== null) {
            $searchLower = strtolower($search);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => str_contains(strtolower($entry['message']), $searchLower)
                    || ($entry['stack_trace'] !== null && str_contains(strtolower($entry['stack_trace']), $searchLower)),
            ));
        }

        return $entries;
    }

    /**
     * Read the last N lines from a file efficiently.
     */
    public function tailFile(string $filePath, int $lines = 500): string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return '';
        }

        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $content = '';
        while (! $file->eof()) {
            $content .= $file->fgets();
        }

        return $content;
    }

    /**
     * @param  array{datetime: string, channel: string, level: string, message: string, extra_lines: list<string>}  $entry
     * @return array{level: string, datetime: string, channel: string, message: string, context: string|null, stack_trace: string|null}
     */
    private function finaliseEntry(array $entry): array
    {
        $extra = implode("\n", $entry['extra_lines']);

        // Separate context/JSON from stack traces
        $stackTrace = null;
        $context = null;

        if ($extra !== '') {
            // Stack traces typically start with #0 or [stacktrace]
            if (preg_match('/^(\[stacktrace\]|#\d+\s)/m', $extra, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = (int) $matches[0][1];
                $context = trim(substr($extra, 0, $offset)) ?: null;
                $stackTrace = trim(substr($extra, $offset)) ?: null;
            } else {
                $context = trim($extra) ?: null;
            }
        }

        return [
            'level' => $entry['level'],
            'datetime' => $entry['datetime'],
            'channel' => $entry['channel'],
            'message' => $entry['message'],
            'context' => $context,
            'stack_trace' => $stackTrace,
        ];
    }
}
