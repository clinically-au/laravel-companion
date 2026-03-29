@extends('companion::dashboard.layout')

@section('content')
    <flux:heading size="xl">Create Agent</flux:heading>
    <flux:subheading>Generate a new agent token for device pairing</flux:subheading>

    <flux:separator class="my-6" />

    <livewire:companion-agent-creator />
@endsection
