<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;

final class EventController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var array<string, list<mixed>> $rawListeners */
        $rawListeners = Event::getRawListeners();

        $events = [];

        foreach ($rawListeners as $event => $listeners) {
            $events[] = [
                'event' => $event,
                'listeners' => collect($listeners)->map(function (mixed $listener) {
                    if (is_string($listener)) {
                        return [
                            'class' => $listener,
                            'queued' => $this->isQueuedListener($listener),
                        ];
                    }

                    if (is_array($listener) && isset($listener[0])) {
                        $class = is_object($listener[0]) ? get_class($listener[0]) : (string) $listener[0];

                        return [
                            'class' => $class.($listener[1] ?? ''),
                            'queued' => $this->isQueuedListener($class),
                        ];
                    }

                    return [
                        'class' => 'Closure',
                        'queued' => false,
                    ];
                })->all(),
            ];
        }

        return $this->respond($events);
    }

    private function isQueuedListener(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, ShouldQueue::class);
    }
}
