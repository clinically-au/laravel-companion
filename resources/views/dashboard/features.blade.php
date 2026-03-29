@extends('companion::dashboard.layout')

@section('content')
    <flux:heading size="xl">Features</flux:heading>
    <flux:subheading>Enabled features and integration status</flux:subheading>

    <flux:separator class="my-6" />

    <livewire:companion-feature-status />
@endsection
