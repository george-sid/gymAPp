<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:pl-64">
            {{-- Mobile backdrop --}}
            <div
                x-show="sidebarOpen"
                x-transition.opacity
                x-on:click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-zinc-900/40 lg:hidden"
                x-cloak
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-zinc-200 bg-white transition-transform lg:translate-x-0"
                x-bind:class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-16 items-center gap-3 border-b border-zinc-200 px-5">
                    <span class="inline-flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-sm font-bold text-white">G</span>
                    <span class="font-semibold">{{ config('app.name') }}</span>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Αρχική</x-nav-link>
                    <x-nav-link :href="route('calendar')" :active="request()->routeIs('calendar')" icon="calendar">Ημερολόγιο</x-nav-link>
                    <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')" icon="users">Πελάτες</x-nav-link>
                    <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')" icon="clipboard">Προγράμματα</x-nav-link>
                    <x-nav-link :href="route('workout-plans.index')" :active="request()->routeIs('workout-plans.*')" icon="list">Προπονήσεις</x-nav-link>
                    <x-nav-link :href="route('exercises.index')" :active="request()->routeIs('exercises.*')" icon="bolt">Ασκήσεις</x-nav-link>
                    <x-nav-link :href="route('availability.index')" :active="request()->routeIs('availability.*')" icon="clock">Διαθεσιμότητα</x-nav-link>
                    <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.*')" icon="settings">Ρυθμίσεις</x-nav-link>
                </nav>

                <div class="border-t border-zinc-200 p-3">
                    <div class="px-3 pb-2 text-sm">
                        <div class="font-medium">{{ auth()->user()->name }}</div>
                        <div class="text-zinc-500">{{ auth()->user()->role->label() }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900">
                            <x-icon name="logout" class="size-5" />
                            Αποσύνδεση
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-zinc-200 bg-white/90 px-4 backdrop-blur lg:px-8">
                    <button type="button" class="-ml-1 rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 lg:hidden" x-on:click="sidebarOpen = true">
                        <x-icon name="menu" class="size-5" />
                        <span class="sr-only">Μενού</span>
                    </button>
                    <h1 class="truncate text-lg font-semibold">{{ $title ?? config('app.name') }}</h1>
                </header>

                <main class="flex-1 px-4 py-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-toast />

        @livewireScriptConfig
    </body>
</html>
