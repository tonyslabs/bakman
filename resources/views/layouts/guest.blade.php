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
    </head>
    <body class="font-sans text-zinc-200 antialiased">
        <div class="flex min-h-screen flex-col items-center bg-black pt-6 sm:justify-center sm:pt-0">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                <span class="font-display text-2xl tracking-wide text-zinc-100">BACKEND<span class="text-red-500">·</span>MANAGER</span>
            </div>

            <div class="mt-6 w-full overflow-hidden border border-zinc-800 bg-zinc-950 px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
