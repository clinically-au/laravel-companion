@extends('companion::dashboard.layout')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Agents</flux:heading>
            <flux:subheading>Manage connected devices and API clients</flux:subheading>
        </div>
        <flux:button href="{{ route('companion.dashboard.agents.create') }}" variant="primary" icon="plus">
            Create Agent
        </flux:button>
    </div>

    <flux:separator class="my-6" />

    <livewire:companion-agent-list />
@endsection
