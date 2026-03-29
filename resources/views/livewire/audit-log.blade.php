<div>
    <div class="mb-4 flex items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="actionFilter" placeholder="Filter by action..." icon="magnifying-glass" class="max-w-xs" />

        <flux:select wire:model.live="methodFilter" variant="native" class="max-w-[10rem]">
            <option value="">All Methods</option>
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="DELETE">DELETE</option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->entries()">
        <flux:table.columns>
            <flux:table.column>Time</flux:table.column>
            <flux:table.column>Agent</flux:table.column>
            <flux:table.column>Method</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Duration</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->entries() as $entry)
                <flux:table.row>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $entry->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $entry->agent?->name ?? 'Unknown' }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm">{{ $entry->method }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">{{ $entry->action }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$entry->response_code < 400 ? 'green' : 'red'">
                            {{ $entry->response_code }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">{{ $entry->duration_ms }}ms</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button href="{{ route('companion.dashboard.audit.show', $entry) }}" variant="ghost" size="xs" icon="eye">
                            View
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-400">No audit entries found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
