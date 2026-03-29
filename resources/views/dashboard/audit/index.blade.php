@extends('companion::dashboard.layout')

@section('content')
    <flux:heading size="xl">Audit Log</flux:heading>
    <flux:subheading>All API activity from connected agents</flux:subheading>

    <flux:separator class="my-6" />

    <livewire:companion-audit-log />
@endsection
