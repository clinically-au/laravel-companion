<div class="space-y-8">
    {{-- Agent Info --}}
    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <flux:subheading>Name</flux:subheading>
                <flux:text class="font-medium">{{ $this->agent()->name }}</flux:text>
            </div>
            <div>
                <flux:subheading>Status</flux:subheading>
                <div class="mt-0.5">
                    @if($this->agent()->isRevoked())
                        <flux:badge color="red">Revoked</flux:badge>
                    @elseif($this->agent()->isExpired())
                        <flux:badge color="amber">Expired</flux:badge>
                    @else
                        <flux:badge color="green">Active</flux:badge>
                    @endif
                </div>
            </div>
            <div>
                <flux:subheading>Token Prefix</flux:subheading>
                <flux:text class="font-mono text-sm">{{ $this->agent()->token_prefix }}...</flux:text>
            </div>
            <div>
                <flux:subheading>Last Seen</flux:subheading>
                <flux:text>{{ $this->agent()->last_seen_at?->diffForHumans() ?? 'Never' }}</flux:text>
            </div>
            <div>
                <flux:subheading>Last IP</flux:subheading>
                <flux:text class="font-mono text-sm">{{ $this->agent()->last_ip ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:subheading>Expires</flux:subheading>
                <flux:text>{{ $this->agent()->expires_at?->toDateTimeString() ?? 'Never' }}</flux:text>
            </div>
            <div>
                <flux:subheading>Created</flux:subheading>
                <flux:text>{{ $this->agent()->created_at->toDateTimeString() }}</flux:text>
            </div>
        </div>

        <flux:separator class="my-6" />

        {{-- Scopes --}}
        <flux:subheading>Scopes</flux:subheading>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach($this->agent()->scopes as $scope)
                <flux:badge size="sm" color="zinc">{{ $scope }}</flux:badge>
            @endforeach
        </div>

        {{-- Revoke --}}
        @if($this->agent()->isActive())
            <flux:separator class="my-6" />
            <flux:button wire:click="revokeAgent" wire:confirm="Revoke this agent? This cannot be undone." variant="danger" icon="x-mark">
                Revoke Agent
            </flux:button>
        @endif
    </div>

    {{-- Recent Activity --}}
    <div>
        <flux:heading size="lg">Recent Activity</flux:heading>

        @if($this->recentActivity()->isEmpty())
            <flux:text class="mt-2 text-zinc-400">No activity recorded.</flux:text>
        @else
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Time</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                    <flux:table.column>Method</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Duration</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->recentActivity() as $entry)
                        <flux:table.row>
                            <flux:table.cell class="text-xs text-zinc-500">{{ $entry->created_at->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $entry->action }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ $entry->method }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$entry->response_code < 400 ? 'green' : 'red'">
                                    {{ $entry->response_code }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">{{ $entry->duration_ms }}ms</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
