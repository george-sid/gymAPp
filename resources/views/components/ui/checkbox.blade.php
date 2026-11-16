@props(['label'])

<label class="inline-flex items-center gap-2 text-sm text-zinc-700">
    <input type="checkbox" {{ $attributes->merge(['class' => 'size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500']) }} />
    {{ $label }}
</label>
