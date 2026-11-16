<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-100 font-sans text-zinc-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 text-center">
                    <span class="inline-flex size-12 items-center justify-center rounded-xl bg-emerald-600 text-lg font-bold text-white">G</span>
                    <h1 class="mt-4 text-xl font-semibold">{{ config('app.name') }}</h1>
                </div>

                {{ $slot }}
            </div>
        </main>
    </body>
</html>
