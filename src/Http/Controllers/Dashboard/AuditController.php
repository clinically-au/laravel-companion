<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Dashboard;

use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = CompanionAuditLog::with('agent')
            ->latest('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', 'LIKE', "%{$action}%");
        }

        if ($agentId = $request->query('agent_id')) {
            $query->where('agent_id', $agentId);
        }

        return view('companion::dashboard.audit.index', [ // @phpstan-ignore argument.type
            'entries' => $query->paginate(25),
        ]);
    }

    public function show(string $entry): View
    {
        return view('companion::dashboard.audit.show', [ // @phpstan-ignore argument.type
            'entry' => CompanionAuditLog::with('agent')->findOrFail($entry),
        ]);
    }
}
