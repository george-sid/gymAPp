@props(['href', 'active' => false, 'icon' => null])

<a
    href="{{ $href }}"
    wire:navigate
    @class([
        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
        'bg-emerald-50 text-emerald-700' => $active,
        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! $active,
    ])
    @if ($active) aria-current="page" @endif
>
    @if ($icon)
        <x-icon :name="$icon" class="size-5 shrink-0" />
    @endif
    {{ $slot }}
</a>
