<div>
    <x-ui.page-header description="Ό,τι προσφέρεις: personal, ομαδικά, online. Η χωρητικότητα 1 σημαίνει personal.">
        <x-slot:actions>
            <x-ui.button wire:click="create" icon="plus">Νέο πρόγραμμα</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($this->services->isEmpty())
        <x-ui.card>
            <p class="py-6 text-center text-sm text-zinc-500">Δεν υπάρχουν προγράμματα ακόμα. Φτιάξε το πρώτο, π.χ. «Personal 1:1».</p>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->services as $service)
                <article wire:key="service-{{ $service->id }}" class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs">
                    @if ($service->image_url)
                        <img src="{{ $service->image_url }}" alt="" class="h-36 w-full object-cover" />
                    @else
                        <div class="h-2" style="background-color: {{ $service->color }}"></div>
                    @endif

                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="flex items-center gap-2 font-semibold">
                                <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $service->color }}"></span>
                                {{ $service->name }}
                            </h3>
                            @unless ($service->is_public)
                                <x-ui.badge>Κρυφό</x-ui.badge>
                            @endunless
                        </div>

                        @if ($service->description)
                            <p class="mt-2 line-clamp-3 text-sm text-zinc-600">{{ $service->description }}</p>
                        @endif

                        <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600">
                            <div><dt class="sr-only">Διάρκεια</dt><dd>{{ $service->duration_min }}′</dd></div>
                            <div>
                                <dt class="sr-only">Τιμή</dt>
                                <dd>{{ $service->price !== null ? \Illuminate\Support\Number::currency($service->price, 'EUR', 'el') : 'Χωρίς τιμή' }}</dd>
                            </div>
                            <div>
                                <dt class="sr-only">Χωρητικότητα</dt>
                                <dd>{{ $service->isGroup() ? 'Ομαδικό · έως '.$service->capacity.' άτομα' : 'Personal 1:1' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-auto flex flex-wrap gap-2 pt-4">
                            <x-ui.button variant="secondary" size="sm" icon="pencil" wire:click="edit({{ $service->id }})">Επεξεργασία</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" wire:click="toggleVisibility({{ $service->id }})">
                                {{ $service->is_public ? 'Απόκρυψη' : 'Εμφάνιση' }}
                            </x-ui.button>
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                class="ml-auto text-red-600 hover:bg-red-50 hover:text-red-700"
                                wire:click="delete({{ $service->id }})"
                                wire:confirm="Να διαγραφεί το πρόγραμμα «{{ $service->name }}»;"
                            ><span class="sr-only">Διαγραφή</span></x-ui.button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($showForm)
        <x-ui.modal :title="$form->service ? 'Επεξεργασία προγράμματος' : 'Νέο πρόγραμμα'" close="closeForm">
            <form id="service-form" wire:submit="save" class="space-y-4">
                <x-ui.input wire:model="form.name" label="Όνομα" placeholder="π.χ. Personal 1:1" required />
                <x-ui.textarea wire:model="form.description" label="Περιγραφή" rows="3" />

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <x-ui.input wire:model="form.duration_min" label="Διάρκεια (λεπτά)" type="number" min="5" step="5" required />
                    <x-ui.input wire:model="form.price" label="Τιμή (€)" type="number" min="0" step="0.01" />
                    <x-ui.input wire:model="form.capacity" label="Μέγ. άτομα" type="number" min="1" required hint="1 = personal" />
                </div>

                <div class="flex flex-wrap items-end gap-6">
                    <x-ui.field label="Χρώμα στο ημερολόγιο" for="form.color">
                        <input type="color" wire:model="form.color" class="h-10 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1" />
                    </x-ui.field>
                    <x-ui.checkbox wire:model="form.is_public" label="Ορατό στους πελάτες" />
                </div>

                <x-ui.field label="Φωτογραφία" for="form.image">
                    <input type="file" wire:model="form.image" accept="image/*" class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200" />
                    <div wire:loading wire:target="form.image" class="text-xs text-zinc-500">Ανέβασμα…</div>
                </x-ui.field>

                @if ($form->image)
                    <img src="{{ $form->image->temporaryUrl() }}" alt="" class="h-32 rounded-lg object-cover" />
                @elseif ($form->service?->image_url && ! $form->remove_image)
                    <div class="flex items-center gap-3">
                        <img src="{{ $form->service->image_url }}" alt="" class="h-20 rounded-lg object-cover" />
                        <x-ui.checkbox wire:model.live="form.remove_image" label="Αφαίρεση φωτογραφίας" />
                    </div>
                @endif
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeForm">Άκυρο</x-ui.button>
                <x-ui.button type="submit" form="service-form" wire:loading.attr="disabled" wire:target="save,form.image">Αποθήκευση</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
