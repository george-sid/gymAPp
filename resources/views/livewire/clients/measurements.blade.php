<div class="space-y-6">
    @php
        $measurements = $this->measurements;
        $points = $this->chartPoints;
        $current = $metrics[$metric] ?? $metrics['weight'];
        $photos = $measurements->reverse()->flatMap->photos->values();
        $format = fn (?float $value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    @endphp

    <x-ui.card :title="$current['label'].' στον χρόνο'">
        <x-slot:actions>
            <select wire:model.live="metric" class="rounded-lg border border-zinc-300 py-1.5 pr-8 pl-2 text-sm" aria-label="Μέγεθος">
                @foreach ($metrics as $key => $definition)
                    <option value="{{ $key }}">{{ $definition['label'] }} ({{ $definition['unit'] }})</option>
                @endforeach
            </select>
            <x-ui.button size="sm" icon="plus" wire:click="create">Νέα μέτρηση</x-ui.button>
        </x-slot:actions>

        @if (count($points) === 0)
            <p class="py-6 text-center text-sm text-zinc-500">Δεν υπάρχουν μετρήσεις για {{ mb_strtolower($current['label']) }}.</p>
        @else
            @php($change = count($points) > 1 ? end($points)['value'] - $points[0]['value'] : null)
            @if ($change !== null)
                <p class="mb-3 text-sm text-zinc-600">
                    Μεταβολή από {{ $points[0]['label'] }}:
                    <span class="font-semibold text-zinc-900 tabular-nums">{{ $change > 0 ? '+' : '' }}{{ $format($change) }} {{ $current['unit'] }}</span>
                </p>
            @endif
            <div wire:key="chart-{{ $metric }}-{{ md5(json_encode($points)) }}">
                <x-line-chart :points="$points" :unit="$current['unit']" :title="$current['label']" />
            </div>
        @endif
    </x-ui.card>

    <x-ui.card title="Ιστορικό μετρήσεων" :padding="false">
        @if ($measurements->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-zinc-500">Καμία μέτρηση ακόμα.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Ημερομηνία</th>
                            @foreach ($metrics as $definition)
                                <th class="px-3 py-3 text-right whitespace-nowrap">{{ $definition['label'] }} <span class="normal-case">({{ $definition['unit'] }})</span></th>
                            @endforeach
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($measurements as $measurement)
                            <tr wire:key="measurement-{{ $measurement->id }}">
                                <td class="px-5 py-2 font-medium whitespace-nowrap">
                                    {{ $measurement->measured_on->format('d/m/Y') }}
                                    @if ($measurement->photos->isNotEmpty())
                                        <span class="ml-1 text-xs font-normal text-zinc-500">· {{ $measurement->photos->count() }} φωτ.</span>
                                    @endif
                                    @if ($measurement->notes)
                                        <div class="max-w-xs truncate text-xs font-normal text-zinc-500" title="{{ $measurement->notes }}">{{ $measurement->notes }}</div>
                                    @endif
                                </td>
                                @foreach (array_keys($metrics) as $key)
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $format($measurement->{$key}) }}</td>
                                @endforeach
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" wire:click="edit({{ $measurement->id }})" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700">
                                            <x-icon name="pencil" class="size-4" /><span class="sr-only">Επεξεργασία</span>
                                        </button>
                                        <button type="button" wire:click="delete({{ $measurement->id }})" wire:confirm="Να διαγραφεί η μέτρηση και οι φωτογραφίες της;" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-red-600">
                                            <x-icon name="trash" class="size-4" /><span class="sr-only">Διαγραφή</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($photos->isNotEmpty())
        <x-ui.card title="Φωτογραφίες προόδου">
            @if ($photos->count() > 1)
                <div class="mb-5 grid grid-cols-2 gap-4 sm:max-w-xl">
                    @foreach (['Πριν' => $photos->first(), 'Τώρα' => $photos->last()] as $label => $photo)
                        <figure>
                            <img src="{{ route('measurement-photos.show', $photo) }}" alt="{{ $label }}" class="aspect-[3/4] w-full rounded-lg object-cover" loading="lazy" />
                            <figcaption class="mt-1 text-xs text-zinc-500">{{ $label }} · {{ $measurements->firstWhere('id', $photo->measurement_id)?->measured_on->format('d/m/Y') }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8">
                @foreach ($photos as $photo)
                    <div wire:key="photo-{{ $photo->id }}" class="group relative">
                        <a href="{{ route('measurement-photos.show', $photo) }}" target="_blank">
                            <img src="{{ route('measurement-photos.show', $photo) }}" alt="" class="aspect-square w-full rounded-lg object-cover" loading="lazy" />
                        </a>
                        <button type="button" wire:click="deletePhoto({{ $photo->id }})" wire:confirm="Να διαγραφεί η φωτογραφία;" class="absolute top-1 right-1 hidden rounded-md bg-white/90 p-1 text-red-600 shadow group-hover:block">
                            <x-icon name="trash" class="size-4" /><span class="sr-only">Διαγραφή</span>
                        </button>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-zinc-500">Οι φωτογραφίες αποθηκεύονται ιδιωτικά και τις βλέπει μόνο ο trainer.</p>
        </x-ui.card>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$editingId ? 'Επεξεργασία μέτρησης' : 'Νέα μέτρηση'" close="closeForm">
            <form id="measurement-form" wire:submit="save" class="space-y-4">
                <x-ui.input wire:model="measured_on" label="Ημερομηνία" type="date" />

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($metrics as $key => $definition)
                        <x-ui.input wire:model="values.{{ $key }}" :label="$definition['label'].' ('.$definition['unit'].')'" type="number" step="0.1" min="0" inputmode="decimal" />
                    @endforeach
                </div>
                @error('values') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <x-ui.textarea wire:model="notes" label="Σημειώσεις" rows="2" />

                <x-ui.field label="Φωτογραφίες προόδου" for="photos">
                    <input type="file" wire:model="photos" accept="image/*" multiple class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200" />
                    <div wire:loading wire:target="photos" class="text-xs text-zinc-500">Ανέβασμα…</div>
                    @error('photos.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </x-ui.field>

                @if ($photos !== [])
                    <div class="flex flex-wrap gap-2">
                        @foreach ($photos as $photo)
                            <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-lg object-cover" />
                        @endforeach
                    </div>
                @endif
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeForm">Άκυρο</x-ui.button>
                <x-ui.button type="submit" form="measurement-form" wire:loading.attr="disabled" wire:target="save,photos">Αποθήκευση</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
