@extends('companion::dashboard.layout')

@section('content')
    <flux:heading size="xl">Overview</flux:heading>
    <flux:subheading>{{ config('app.name') }} companion status</flux:subheading>

    <flux:separator class="my-6" />

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>Active Agents</flux:subheading>
            <div class="mt-1 text-2xl font-semibold">{{ $agentCount }}</div>
            <flux:text size="sm" class="text-zinc-400">of {{ $totalAgents }} total</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>Environment</flux:subheading>
            <div class="mt-1 text-2xl font-semibold">{{ config('app.env') }}</div>
            <flux:text size="sm" class="text-zinc-400">Laravel {{ app()->version() }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:subheading>API Path</flux:subheading>
            <div class="mt-1 text-2xl font-semibold font-mono">/{{ config('companion.path') }}</div>
            <flux:text size="sm" class="text-zinc-400">{{ config('companion.rate_limit.api') }} req/min</flux:text>
        </div>
    </div>

    {{-- Feature Status --}}
    <div class="mt-8">
        <livewire:companion-feature-status />
    </div>

    {{-- Recent Activity --}}
    <div class="mt-8">
        <flux:heading size="lg">Recent Activity</flux:heading>

        @if($recentAudit->isEmpty())
            <flux:text class="mt-2 text-zinc-400">No recent activity.</flux:text>
        @else
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Time</flux:table.column>
                    <flux:table.column>Agent</flux:table.column>
                    <flux:table.column>Action</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($recentAudit as $entry)
                        <flux:table.row>
                            <flux:table.cell class="text-xs text-zinc-500">{{ $entry->created_at->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>{{ $entry->agent?->name ?? 'Unknown' }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $entry->action }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$entry->response_code < 400 ? 'green' : 'red'">
                                    {{ $entry->response_code }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
@endsection
