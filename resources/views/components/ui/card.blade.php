@props(['title' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white shadow-xs']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-3">
            <h2 class="font-semibold">{{ $title }}</h2>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
