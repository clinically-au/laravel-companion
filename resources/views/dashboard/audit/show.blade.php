@extends('companion::dashboard.layout')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Audit Entry</flux:heading>
            <flux:subheading>{{ $entry->action }} — {{ $entry->created_at->toDateTimeString() }}</flux:subheading>
        </div>
        <flux:button href="{{ route('companion.dashboard.audit.index') }}" variant="ghost" icon="arrow-left">
            Back to Audit Log
        </flux:button>
    </div>

    <flux:separator class="my-6" />

    <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <flux:subheading>Agent</flux:subheading>
                <flux:text class="font-medium">{{ $entry->agent?->name ?? 'Unknown' }}</flux:text>
            </div>
            <div>
                <flux:subheading>Action</flux:subheading>
                <flux:text class="font-mono">{{ $entry->action }}</flux:text>
            </div>
            <div>
                <flux:subheading>Method</flux:subheading>
                <flux:badge size="sm">{{ $entry->method }}</flux:badge>
            </div>
            <div>
                <flux:subheading>Path</flux:subheading>
                <flux:text class="font-mono text-sm">{{ $entry->path }}</flux:text>
            </div>
            <div>
                <flux:subheading>Status</flux:subheading>
                <flux:badge size="sm" :color="$entry->response_code < 400 ? 'green' : 'red'">
                    {{ $entry->response_code }}
                </flux:badge>
            </div>
            <div>
                <flux:subheading>Duration</flux:subheading>
                <flux:text>{{ $entry->duration_ms }}ms</flux:text>
            </div>
            <div>
                <flux:subheading>IP</flux:subheading>
                <flux:text class="font-mono text-sm">{{ $entry->ip }}</flux:text>
            </div>
            <div>
                <flux:subheading>Time</flux:subheading>
                <flux:text>{{ $entry->created_at->toDateTimeString() }}</flux:text>
            </div>
        </div>

        @if($entry->payload)
            <flux:separator class="my-6" />
            <flux:subheading>Payload</flux:subheading>
            <pre class="mt-2 overflow-auto rounded-lg bg-zinc-100 p-4 text-xs dark:bg-zinc-800">{{ json_encode($entry->payload, JSON_PRETTY_PRINT) }}</pre>
        @endif
    </div>
@endsection
