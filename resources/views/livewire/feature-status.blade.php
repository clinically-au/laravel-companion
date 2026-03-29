<div>
    <flux:heading size="lg">Feature Status</flux:heading>

    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
        @foreach($features as $name => $enabled)
            <div class="rounded-lg border px-4 py-3 text-center {{ $enabled ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' }}">
                <flux:text class="font-medium {{ $enabled ? 'text-green-800 dark:text-green-300' : 'text-zinc-400' }}">
                    {{ $name }}
                </flux:text>
                <flux:badge size="sm" :color="$enabled ? 'green' : 'zinc'" class="mt-1">
                    {{ $enabled ? 'enabled' : 'disabled' }}
                </flux:badge>
            </div>
        @endforeach
    </div>
</div>
