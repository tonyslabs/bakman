<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">

        <title>{{ config('app.name', 'Backend Manager') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Doto:wght@400..900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script>
            tailwind = { config: {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Figtree', 'ui-sans-serif', 'system-ui'],
                            display: ['Doto', 'ui-sans-serif', 'system-ui'],
                        },
                    },
                },
            } };
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <style>[x-cloak]{display:none!important}</style>
    </head>
    <body class="font-sans antialiased bg-black text-zinc-200">
        <div x-data="{ mobileOpen: false }" class="min-h-screen flex">

            <!-- Mobile overlay -->
            <div x-show="mobileOpen" x-cloak @click="mobileOpen = false"
                 class="fixed inset-0 z-30 bg-black/70 lg:hidden"></div>

            <!-- Sidebar -->
            <aside
                :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-zinc-800 bg-zinc-950 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
            >
                <!-- Brand -->
                <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-2 border-b border-zinc-800 px-5 shrink-0">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span class="font-display text-lg tracking-wide text-zinc-100">BACKEND<span class="text-red-500">·</span>MANAGER</span>
                </a>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-sidebar-link>

                    <x-sidebar-group label="Database" :open="request()->routeIs('database-connections.*') || request()->routeIs('database-migrations.*') || request()->routeIs('database-diff.*') || request()->routeIs('database-diff-syncs.*')">
                        <x-sidebar-link :href="route('database-connections.index')" :active="request()->routeIs('database-connections.*')">
                            {{ __('Conexiones') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('database-migrations.index')" :active="request()->routeIs('database-migrations.*')">
                            {{ __('Migraciones') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('database-diff.create')" :active="request()->routeIs('database-diff.*')">
                            {{ __('Comparar') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('database-diff-syncs.index')" :active="request()->routeIs('database-diff-syncs.*')">
                            {{ __('Sincronizaciones') }}
                        </x-sidebar-link>
                    </x-sidebar-group>

                    <x-sidebar-group label="Almacenamiento" :open="request()->routeIs('files.*')">
                        <x-sidebar-link :href="route('files.index')" :active="request()->routeIs('files.*')">
                            {{ config('backups.browse_label') }}
                        </x-sidebar-link>
                    </x-sidebar-group>

                    <x-sidebar-group label="Lab" :open="request()->routeIs('lab.*')">
                        <x-sidebar-link :href="route('lab.show', 'develop')" :active="request()->route('module') === 'develop'">
                            {{ __('Develop') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('lab.show', 'homelab')" :active="request()->route('module') === 'homelab'">
                            {{ __('Homelab') }}
                        </x-sidebar-link>
                    </x-sidebar-group>

                    <x-sidebar-group label="Jobs" :open="request()->routeIs('monitor') || request()->routeIs('backup-jobs.*')">
                        <x-sidebar-link :href="route('monitor')" :active="request()->routeIs('monitor')">
                            {{ __('Monitor') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('backup-jobs.index')" :active="request()->routeIs('backup-jobs.*')">
                            {{ __('Jobs') }}
                        </x-sidebar-link>
                    </x-sidebar-group>

                    <x-sidebar-group label="SSH" :open="request()->routeIs('targets.*') || request()->routeIs('settings.ssh-key')">
                        <x-sidebar-link :href="route('targets.index')" :active="request()->routeIs('targets.*')">
                            {{ __('Targets') }}
                        </x-sidebar-link>
                        <x-sidebar-link :href="route('settings.ssh-key')" :active="request()->routeIs('settings.ssh-key')">
                            {{ __('Clave SSH') }}
                        </x-sidebar-link>
                    </x-sidebar-group>
                </nav>

                <!-- User area -->
                <div class="border-t border-zinc-800 p-3">
                    <x-dropdown align="left" width="56">
                        <x-slot name="trigger">
                            <button class="flex w-full items-center justify-between rounded-md px-2 py-2 text-sm text-zinc-300 hover:bg-zinc-900 focus:outline-none">
                                <span class="truncate">{{ Auth::user()->name }}</span>
                                <svg class="h-4 w-4 shrink-0 fill-current text-zinc-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </aside>

            <!-- Main column -->
            <div class="flex min-w-0 flex-1 flex-col lg:pl-0">
                <!-- Mobile top bar -->
                <div class="flex h-14 items-center gap-3 border-b border-zinc-800 bg-zinc-950 px-4 lg:hidden">
                    <button @click="mobileOpen = true" class="rounded-md p-2 text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100 focus:outline-none">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="font-display text-zinc-100">BACKEND<span class="text-red-500">·</span>MANAGER</span>
                </div>

                @isset($header)
                    <header class="border-b border-zinc-800 bg-zinc-950/60">
                        <div class="mx-auto max-w-7xl px-4 py-6 font-display sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
