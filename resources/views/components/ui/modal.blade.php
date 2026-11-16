{{-- Server-rendered modal: wrap in @if ($showX) ... @endif and call `close` to hide it. --}}
@props(['title', 'close', 'maxWidth' => 'max-w-lg'])

<div class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.{{ $close }}()">
    <div class="fixed inset-0 bg-zinc-900/50" wire:click="{{ $close }}"></div>

    <div class="relative flex max-h-[90vh] w-full {{ $maxWidth }} flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
        <header class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
            <button type="button" class="rounded-lg p-1 text-zinc-500 hover:bg-zinc-100" wire:click="{{ $close }}">
                <x-icon name="x" class="size-5" />
                <span class="sr-only">Κλείσιμο</span>
            </button>
        </header>

        <div class="overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
