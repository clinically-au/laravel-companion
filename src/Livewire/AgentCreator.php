<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Services\TokenService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class AgentCreator extends Component
{
    public string $name = '';

    /** @var list<string> */
    public array $scopes = [];

    public int $expiryDays = 90;

    public ?string $plainToken = null;

    public ?string $agentId = null;

    public function mount(): void
    {
        $this->expiryDays = (int) config('companion.agents.default_expiry_days', 90);
    }

    public function applyPreset(string $preset): void
    {
        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);
        $this->scopes = $tokenService->resolvePreset($preset);
    }

    public function createAgent(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'required|array|min:1',
        ]);

        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);

        if ($tokenService->maxAgentsReached()) {
            $this->addError('name', 'Maximum number of agents reached.');

            return;
        }

        $result = $tokenService->createAgent(
            name: $this->name,
            scopes: $this->scopes,
            expiresAt: $this->expiryDays > 0 ? now()->addDays($this->expiryDays) : null,
            creator: auth()->user(),
        );

        $this->plainToken = $result->plainToken;
        $this->agentId = $result->agent->id;

        $this->dispatch('agent-created');
    }

    public function render(): View
    {
        return view('companion::livewire.agent-creator', [ // @phpstan-ignore argument.type
            'availableScopes' => (array) config('companion.scopes', []),
            'presets' => array_keys((array) config('companion.scope_presets', [])),
        ]);
    }
}
