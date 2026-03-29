<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Dashboard;

use Clinically\Companion\FeatureRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class FeatureController extends Controller
{
    public function index(FeatureRegistry $features): View
    {
        $featureNames = [
            'environment', 'models', 'routes', 'commands', 'queues',
            'cache', 'config', 'logs', 'schedule', 'migrations',
            'events', 'horizon', 'pulse', 'telescope', 'dashboard',
        ];

        $status = [];
        foreach ($featureNames as $name) {
            $status[$name] = [
                'enabled' => $features->enabled($name),
                'config' => config("companion.features.{$name}"),
            ];
        }

        return view('companion::dashboard.features', [ // @phpstan-ignore argument.type
            'features' => $status,
        ]);
    }
}
