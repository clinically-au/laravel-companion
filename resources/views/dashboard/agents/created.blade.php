@extends('companion::dashboard.layout')

@section('content')
    <flux:heading size="xl">Agent Created</flux:heading>
    <flux:subheading>Save the token below — it will not be shown again</flux:subheading>

    <flux:separator class="my-6" />

    <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-700 dark:bg-amber-900/20">
        <flux:heading size="sm" class="text-amber-800 dark:text-amber-300">Authentication Token</flux:heading>
        <flux:text size="sm" class="mt-1 text-amber-700 dark:text-amber-400">
            This token is shown only once. Copy it now.
        </flux:text>
        <div class="mt-3">
            <flux:input readonly :value="$plainToken" copyable class="font-mono" />
        </div>
    </div>

    <div class="mt-6">
        <livewire:companion-qr-code :agent="$agent" :token="$plainToken" />
    </div>

    <div class="mt-6">
        <flux:button href="{{ route('companion.dashboard.agents.show', $agent) }}" variant="primary" icon="arrow-right">
            View Agent Details
        </flux:button>
    </div>
@endsection
