{{-- Label + control + validation error. `for` is the Livewire property / error key. --}}
@props(['label' => null, 'for' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-zinc-700">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-zinc-500">{{ $hint }}</p>
    @endif

    @if ($for)
        @error($for)
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
