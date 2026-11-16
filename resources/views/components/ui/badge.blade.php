@props(['color' => 'zinc'])

@php
    $colors = [
        'zinc' => 'bg-zinc-100 text-zinc-700',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'red' => 'bg-red-100 text-red-700',
        'sky' => 'bg-sky-100 text-sky-800',
        'violet' => 'bg-violet-100 text-violet-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($colors[$color] ?? $colors['zinc'])]) }}>
    {{ $slot }}
</span>
