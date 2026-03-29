<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

final class AuditLog extends Component
{
    use WithPagination;

    public ?string $agentId = null;

    public string $actionFilter = '';

    public string $methodFilter = '';

    public function mount(?string $agent = null): void
    {
        $this->agentId = $agent;
    }

    /**
     * @return LengthAwarePaginator<CompanionAuditLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $query = CompanionAuditLog::with('agent')
            ->latest('created_at');

        if ($this->agentId) {
            $query->where('agent_id', $this->agentId);
        }

        if ($this->actionFilter !== '') {
            $query->where('action', 'LIKE', "%{$this->actionFilter}%");
        }

        if ($this->methodFilter !== '') {
            $query->where('method', $this->methodFilter);
        }

        return $query->paginate(25);
    }

    public function render(): View
    {
        return view('companion::livewire.audit-log'); // @phpstan-ignore argument.type
    }
}
