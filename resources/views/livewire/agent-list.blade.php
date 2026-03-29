<div>
    <flux:tabs wire:model="filter" variant="segmented" class="mb-6">
        <flux:tab name="all">All</flux:tab>
        <flux:tab name="active">Active</flux:tab>
        <flux:tab name="revoked">Revoked</flux:tab>
        <flux:tab name="expired">Expired</flux:tab>
    </flux:tabs>

    <flux:table :paginate="$this->agents()">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Scopes</flux:table.column>
            <flux:table.column>Last Seen</flux:table.column>
            <flux:table.column>Expires</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->agents() as $agent)
                <flux:table.row>
                    <flux:table.cell>
                        <a href="{{ route('companion.dashboard.agents.show', $agent) }}" class="font-medium hover:underline">
                            {{ $agent->name }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($agent->isRevoked())
                            <flux:badge size="sm" color="red">Revoked</flux:badge>
                        @elseif($agent->isExpired())
                            <flux:badge size="sm" color="amber">Expired</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">Active</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ count($agent->scopes) }} scopes</flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $agent->last_seen_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $agent->expires_at?->toDateString() ?? 'Never' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @if($agent->isActive())
                            <flux:button wire:click="revokeAgent('{{ $agent->id }}')" wire:confirm="Revoke this agent? This cannot be undone." variant="ghost" size="sm" class="text-red-600">
                                Revoke
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-400">No agents found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
