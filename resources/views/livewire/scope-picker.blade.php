<div>
    <flux:subheading>Scopes</flux:subheading>

    {{-- Preset buttons --}}
    <div class="mt-2 flex gap-2">
        @foreach($presets as $preset)
            <flux:button wire:click="applyPreset('{{ $preset }}')" variant="subtle" size="xs">
                {{ ucfirst($preset) }}
            </flux:button>
        @endforeach
    </div>

    {{-- Scope checkboxes --}}
    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach($availableScopes as $scope)
            <flux:checkbox
                wire:click="toggleScope('{{ $scope }}')"
                :checked="in_array($scope, $selected)"
                :label="$scope"
            />
        @endforeach
    </div>
</div>
