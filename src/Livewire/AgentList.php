<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Events\AgentRevoked;
use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

final class AgentList extends Component
{
    use WithPagination;

    public string $filter = 'all';

    public function revokeAgent(string $agentId): void
    {
        $agent = CompanionAgent::findOrFail($agentId);
        $agent->revoke();

        AgentRevoked::dispatch($agent, auth()->id());
    }

    /**
     * @return LengthAwarePaginator<CompanionAgent>
     */
    #[Computed]
    public function agents(): LengthAwarePaginator
    {
        $query = CompanionAgent::latest();

        return match ($this->filter) {
            'active' => $query->active()->paginate(20),
            'revoked' => $query->revoked()->paginate(20),
            'expired' => $query->expired()->paginate(20),
            default => $query->paginate(20),
        };
    }

    public function render(): View
    {
        return view('companion::livewire.agent-list'); // @phpstan-ignore argument.type
    }
}
