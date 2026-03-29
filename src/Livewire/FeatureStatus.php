<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\FeatureRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class FeatureStatus extends Component
{
    private const FEATURES = [
        'environment', 'models', 'routes', 'commands', 'queues',
        'cache', 'config', 'logs', 'schedule', 'migrations',
        'events', 'horizon', 'pulse', 'telescope', 'dashboard',
    ];

    public function render(): View
    {
        /** @var FeatureRegistry $features */
        $features = app(FeatureRegistry::class);

        $status = [];
        foreach (self::FEATURES as $name) {
            $status[$name] = $features->enabled($name);
        }

        return view('companion::livewire.feature-status', [ // @phpstan-ignore argument.type
            'features' => $status,
        ]);
    }
}
