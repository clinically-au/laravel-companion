<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Events\AgentRevoked;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class AgentDetail extends Component
{
    public string $agentId;

    public function mount(CompanionAgent|string $agent): void
    {
        $this->agentId = $agent instanceof CompanionAgent ? $agent->id : $agent;
    }

    #[Computed]
    public function agent(): CompanionAgent
    {
        return CompanionAgent::findOrFail($this->agentId);
    }

    /**
     * @return Collection<int, CompanionAuditLog>
     */
    #[Computed]
    public function recentActivity(): Collection
    {
        return CompanionAuditLog::where('agent_id', $this->agentId)
            ->latest('created_at')
            ->take(20)
            ->get();
    }

    public function revokeAgent(): void
    {
        $agent = $this->agent();
        $agent->revoke();

        AgentRevoked::dispatch($agent, auth()->user());
    }

    public function render(): View
    {
        return view('companion::livewire.agent-detail'); // @phpstan-ignore argument.type
    }
}
