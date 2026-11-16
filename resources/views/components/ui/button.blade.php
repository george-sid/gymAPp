@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null])

@php
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
        'px-3 py-1.5 text-sm' => $size === 'sm',
        'px-4 py-2 text-sm' => $size === 'md',
        'bg-emerald-600 text-white hover:bg-emerald-700' => $variant === 'primary',
        'border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50' => $variant === 'secondary',
        'bg-red-600 text-white hover:bg-red-700' => $variant === 'danger',
        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => $variant === 'ghost',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </button>
@endif
