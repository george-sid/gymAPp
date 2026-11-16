@props(['label' => null, 'name' => null, 'hint' => null])

@php
    $name ??= $attributes->wire('model')->value();
@endphp

<x-ui.field :label="$label" :for="$name" :hint="$hint">
    <textarea
        id="{{ $name }}"
        {{ $attributes->merge(['rows' => 3])->class([
            'block w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-xs focus:outline-none focus:ring-2',
            'border-red-400 focus:border-red-500 focus:ring-red-200' => $name && $errors->has($name),
            'border-zinc-300 focus:border-emerald-500 focus:ring-emerald-200' => ! ($name && $errors->has($name)),
        ]) }}
    ></textarea>
</x-ui.field>
