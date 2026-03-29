<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;

final class ScheduleController extends Controller
{
    public function __construct(
        private readonly Schedule $schedule,
    ) {}

    public function __invoke(): JsonResponse
    {
        $events = collect($this->schedule->events())
            ->map(fn (Event $event) => $this->formatEvent($event))
            ->values()
            ->all();

        return $this->respond($events);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEvent(Event $event): array
    {
        return [
            'command' => $this->extractCommand($event),
            'expression' => $event->expression,
            'description' => $event->description ?? $this->extractCommand($event),
            'timezone' => $event->timezone,
            'without_overlapping' => $event->withoutOverlapping,
            'on_one_server' => $event->onOneServer,
            'next_due' => $this->getNextDue($event),
        ];
    }

    private function extractCommand(Event $event): string
    {
        $command = $event->command ?? '';

        // Strip the PHP binary and artisan path prefix
        if (str_contains($command, "'artisan'")) {
            $command = (string) preg_replace('/^.*?\'artisan\'\s+/', '', $command);
        } elseif (str_contains($command, 'artisan')) {
            $command = (string) preg_replace('/^.*?artisan\s+/', '', $command);
        }

        return trim($command) ?: ($event->description ?? 'Closure');
    }

    /**
     * @return array<string, string>
     */
    private function getNextDue(Event $event): array
    {
        $cron = new CronExpression($event->expression);
        $timezone = $event->timezone ?: config('app.timezone');
        $next = $cron->getNextRunDate('now', 0, false, $timezone);

        return [
            'local' => $next->format('Y-m-d\TH:i:sP'),
            'utc' => $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
