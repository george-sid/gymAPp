<div>
    <x-ui.page-header>
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative sm:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Αναζήτηση άσκησης" class="block w-full rounded-lg border border-zinc-300 bg-white py-2 pr-3 pl-9 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none" />
            </div>
            <select wire:model.live="muscleGroup" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none">
                <option value="">Όλες οι μυϊκές ομάδες</option>
                @foreach ($muscleGroups as $group)
                    <option value="{{ $group->value }}">{{ $group->label() }}</option>
                @endforeach
            </select>
        </div>

        <x-slot:actions>
            <x-ui.button wire:click="create" icon="plus">Νέα άσκηση</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @forelse ($this->exercises as $exercise)
            <div wire:key="exercise-{{ $exercise->id }}" class="flex items-center gap-4 border-b border-zinc-100 px-5 py-3 last:border-0">
                @if ($exercise->image_url)
                    <img src="{{ $exercise->image_url }}" alt="" class="size-12 shrink-0 rounded-lg object-cover" />
                @else
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400"><x-icon name="bolt" class="size-5" /></div>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $exercise->name }}</div>
                    <div class="flex flex-wrap items-center gap-x-3 text-sm text-zinc-500">
                        <span>{{ $exercise->muscle_group->label() }}</span>
                        @if ($exercise->equipment) <span>{{ $exercise->equipment }}</span> @endif
                        @if ($exercise->video_url)
                            <a href="{{ $exercise->video_url }}" target="_blank" rel="noopener" class="text-emerald-700 hover:underline">Βίντεο</a>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 gap-1">
                    <x-ui.button variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $exercise->id }})"><span class="sr-only">Επεξεργασία</span></x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon="trash" class="text-red-600 hover:bg-red-50" wire:click="delete({{ $exercise->id }})" wire:confirm="Να διαγραφεί η άσκηση «{{ $exercise->name }}»; Θα παραμείνει στα προγράμματα που ήδη τη χρησιμοποιούν."><span class="sr-only">Διαγραφή</span></x-ui.button>
                </div>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-zinc-500">Δεν βρέθηκαν ασκήσεις.</p>
        @endforelse

        @if ($this->exercises->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3">{{ $this->exercises->links() }}</div>
        @endif
    </x-ui.card>

    @if ($showForm)
        <x-ui.modal :title="$form->exercise ? 'Επεξεργασία άσκησης' : 'Νέα άσκηση'" close="closeForm">
            <form id="exercise-form" wire:submit="save" class="space-y-4">
                <x-ui.input wire:model="form.name" label="Όνομα" placeholder="π.χ. Πιέσεις πάγκου" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select wire:model="form.muscle_group" label="Μυϊκή ομάδα">
                        @foreach ($muscleGroups as $group)
                            <option value="{{ $group->value }}">{{ $group->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="form.equipment" label="Εξοπλισμός" placeholder="π.χ. Μπάρα" />
                </div>
                <x-ui.input wire:model="form.video_url" label="Σύνδεσμος βίντεο" type="url" placeholder="https://youtube.com/…" />
                <x-ui.textarea wire:model="form.instructions" label="Οδηγίες εκτέλεσης" rows="4" />
                <x-ui.field label="Φωτογραφία" for="form.image">
                    <input type="file" wire:model="form.image" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200" />
                </x-ui.field>
                @if ($form->image)
                    <img src="{{ $form->image->temporaryUrl() }}" alt="" class="h-28 rounded-lg object-cover" />
                @elseif ($form->exercise?->image_url)
                    <img src="{{ $form->exercise->image_url }}" alt="" class="h-28 rounded-lg object-cover" />
                @endif
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeForm">Άκυρο</x-ui.button>
                <x-ui.button type="submit" form="exercise-form" wire:loading.attr="disabled" wire:target="save,form.image">Αποθήκευση</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
