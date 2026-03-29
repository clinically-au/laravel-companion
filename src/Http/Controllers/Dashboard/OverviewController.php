<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Dashboard;

use Clinically\Companion\FeatureRegistry;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class OverviewController extends Controller
{
    public function index(FeatureRegistry $features): View
    {
        return view('companion::dashboard.overview', [ // @phpstan-ignore argument.type
            'agentCount' => CompanionAgent::active()->count(),
            'totalAgents' => CompanionAgent::count(),
            'recentAudit' => CompanionAuditLog::with('agent')
                ->latest('created_at')
                ->take(10)
                ->get(),
            'features' => $features,
        ]);
    }
}
