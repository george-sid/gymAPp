<div class="max-w-3xl">
    <x-ui.page-header>
        <x-ui.button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            :href="$form->client ? route('clients.show', $form->client) : route('clients.index')"
        >Πίσω</x-ui.button>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-6">
        <x-ui.card title="Στοιχεία">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="form.name" label="Ονοματεπώνυμο" class="sm:col-span-2" required />
                <x-ui.input wire:model="form.phone" label="Τηλέφωνο" type="tel" />
                <x-ui.input wire:model="form.email" label="Email" type="email" hint="Χρειάζεται για τις υπενθυμίσεις ραντεβού." />
                <x-ui.input wire:model="form.birth_date" label="Ημερομηνία γέννησης" type="date" />
                <x-ui.select wire:model="form.gender" label="Φύλο" placeholder="—">
                    @foreach ($genders as $gender)
                        <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.checkbox wire:model="form.is_active" label="Ενεργός πελάτης" />
            </div>
        </x-ui.card>

        <x-ui.card title="Στόχοι & υγεία">
            <div class="space-y-4">
                <x-ui.textarea wire:model="form.goals" label="Στόχοι" rows="3" />
                <x-ui.textarea
                    wire:model="form.medical_notes"
                    label="Ιατρικές σημειώσεις / τραυματισμοί"
                    rows="4"
                    hint="Ιδιωτικές: τις βλέπεις μόνο εσύ και αποθηκεύονται κρυπτογραφημένες."
                />
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <x-ui.button type="submit">
                <span wire:loading.remove wire:target="save">Αποθήκευση</span>
                <span wire:loading wire:target="save">Αποθήκευση…</span>
            </x-ui.button>
        </div>
    </form>
</div>
