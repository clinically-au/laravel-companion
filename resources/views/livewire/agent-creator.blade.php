<div>
    @if($plainToken)
        <div class="rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-800 dark:bg-green-900/20">
            <flux:heading size="sm" class="text-green-800 dark:text-green-300">Agent created successfully!</flux:heading>
            <flux:text size="sm" class="mt-1 text-green-700 dark:text-green-400">
                The token has been displayed above. Save it before navigating away.
            </flux:text>
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
