<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Dashboard;

use Clinically\Companion\Events\AgentRevoked;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\TokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class AgentController extends Controller
{
    public function index(): View
    {
        return view('companion::dashboard.agents.index', [ // @phpstan-ignore argument.type
            'agents' => CompanionAgent::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('companion::dashboard.agents.create', [ // @phpstan-ignore argument.type
            'presets' => (array) config('companion.scope_presets', []),
            'scopes' => (array) config('companion.scopes', []),
        ]);
    }

    public function store(Request $request, TokenService $tokenService): View
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'required|array',
            'scopes.*' => 'string',
            'expiry_days' => 'nullable|integer|min:0',
        ]);

        $expiryDays = $validated['expiry_days'] ?? config('companion.agents.default_expiry_days', 90);

        $result = $tokenService->createAgent(
            name: $validated['name'],
            scopes: $validated['scopes'],
            expiresAt: $expiryDays > 0 ? now()->addDays((int) $expiryDays) : null,
            creator: $request->user(),
        );

        return view('companion::dashboard.agents.created', [ // @phpstan-ignore argument.type
            'agent' => $result->agent,
            'plainToken' => $result->plainToken,
        ]);
    }

    public function show(string $agent): View
    {
        return view('companion::dashboard.agents.show', [ // @phpstan-ignore argument.type
            'agent' => CompanionAgent::findOrFail($agent),
        ]);
    }

    public function destroy(string $agent): RedirectResponse
    {
        $agent = CompanionAgent::findOrFail($agent);
        $agent->revoke();

        AgentRevoked::dispatch($agent, request()->user());

        return redirect()->route('companion.dashboard.agents.index')
            ->with('status', "Agent '{$agent->name}' has been revoked.");
    }
}
