<div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
    <flux:heading size="lg">QR Pairing Code</flux:heading>
    <flux:subheading>Scan with the Companion mobile app to pair</flux:subheading>

    <div class="mt-4">
        @if($qrSvg)
            <div class="flex justify-center rounded-lg bg-white p-6">
                {!! $qrSvg !!}
            </div>
        @else
            <flux:text class="text-zinc-400">QR code generation requires simplesoftwareio/simple-qrcode.</flux:text>
        @endif
    </div>

    @if($agent->expires_at)
        <flux:text size="sm" class="mt-3 text-center text-zinc-400">
            Expires {{ $agent->expires_at->diffForHumans() }}
        </flux:text>
    @endif
</div>
