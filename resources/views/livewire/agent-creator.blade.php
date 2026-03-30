<div>
    @if($plainToken)
        <div class="max-w-lg space-y-6">
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-700 dark:bg-amber-900/20">
                <flux:heading size="sm" class="text-amber-800 dark:text-amber-300">Authentication Token</flux:heading>
                <flux:text size="sm" class="mt-1 text-amber-700 dark:text-amber-400">
                    This token is shown only once. Copy it now.
                </flux:text>
                <div class="mt-3">
                    <flux:input readonly :value="$plainToken" copyable class="font-mono" />
                </div>
            </div>

            <livewire:companion-qr-code :agent="$agentId" :token="$plainToken" />

            <flux:button href="{{ route('companion.dashboard.agents.show', $agentId) }}" variant="primary" icon="arrow-right">
                View Agent Details
            </flux:button>
        </div>
    @else
        <form wire:submit="createAgent" class="max-w-lg space-y-6">
            <flux:input wire:model="name" label="Agent Name" placeholder="Wojt's iPhone" required />

            <flux:input wire:model="expiryDays" label="Expiry (days)" type="number" min="0" description="Set to 0 for never expires" />

            <livewire:companion-scope-picker wire:model="scopes" />
            @error('scopes') <flux:text size="sm" class="text-red-600">{{ $message }}</flux:text> @enderror

            <flux:button type="submit" variant="primary" icon="plus">
                Create Agent
            </flux:button>
        </form>
    @endif
</div>
