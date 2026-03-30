<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Companion — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:sidebar.header>
            <flux:brand name="Companion" class="text-sm" />
            <flux:badge size="sm" color="zinc">{{ config('app.env') }}</flux:badge>
        </flux:sidebar.header>

        <flux:navlist>
            <flux:navlist.item
                href="{{ route('companion.dashboard.overview') }}"
                :current="request()->routeIs('companion.dashboard.overview')"
                icon="home"
            >
                Overview
            </flux:navlist.item>

            <flux:navlist.item
                href="{{ route('companion.dashboard.agents.index') }}"
                :current="request()->routeIs('companion.dashboard.agents.*')"
                icon="device-phone-mobile"
            >
                Agents
            </flux:navlist.item>

            <flux:navlist.item
                href="{{ route('companion.dashboard.audit.index') }}"
                :current="request()->routeIs('companion.dashboard.audit.*')"
                icon="clipboard-document-list"
            >
                Audit Log
            </flux:navlist.item>

            <flux:navlist.item
                href="{{ route('companion.dashboard.features.index') }}"
                :current="request()->routeIs('companion.dashboard.features.*')"
                icon="cog-6-tooth"
            >
                Features
            </flux:navlist.item>
        </flux:navlist>

        <flux:spacer />

        <div class="px-4 pb-4 text-xs text-zinc-400">
            {{ config('app.name') }} &middot; Laravel {{ app()->version() }}
        </div>
    </flux:sidebar>

    <flux:main>
        <flux:sidebar.toggle class="lg:hidden mb-4" icon="bars-2" />

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </flux:main>

    @fluxScripts
</body>
</html>
