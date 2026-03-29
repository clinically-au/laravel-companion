@extends('companion::dashboard.layout')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $agent->name }}</flux:heading>
            <flux:subheading>Agent detail and activity</flux:subheading>
        </div>
        <flux:button href="{{ route('companion.dashboard.agents.index') }}" variant="ghost" icon="arrow-left">
            Back to Agents
        </flux:button>
    </div>

    <flux:separator class="my-6" />

    <livewire:companion-agent-detail :agent="$agent" />
@endsection
