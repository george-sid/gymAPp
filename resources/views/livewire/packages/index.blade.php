<div class="max-w-4xl">
    <x-ui.page-header description="Τα πακέτα που πουλάς. Όταν δίνεις πακέτο σε πελάτη, κρατιούνται οι συνεδρίες και η τιμή εκείνης της στιγμής.">
        <x-slot:actions>
            <x-ui.button wire:click="create" icon="plus">Νέο πακέτο</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @forelse ($this->packages as $package)
            <div wire:key="package-{{ $package->id }}" class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-3 last:border-0">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 font-medium">
                        {{ $package->name }}
                        @unless ($package->is_active)
                            <x-ui.badge>Ανενεργό</x-ui.badge>
                        @endunless
                    </div>
                    <div class="text-sm text-zinc-500">
                        {{ $package->sessions_count }} συνεδρίες
                        · {{ $package->valid_days ? 'ισχύς '.$package->valid_days.' μέρες' : 'χωρίς λήξη' }}
                        · {{ $package->service?->name ?? 'όλα τα προγράμματα' }}
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="font-semibold tabular-nums">{{ \Illuminate\Support\Number::currency($package->price, 'EUR', 'el') }}</span>
                    <x-ui.button variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $package->id }})"><span class="sr-only">Επεξεργασία</span></x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon="trash" class="text-red-600 hover:bg-red-50" wire:click="delete({{ $package->id }})" wire:confirm="Να διαγραφεί το πακέτο «{{ $package->name }}»; Τα πακέτα που έχουν ήδη δοθεί σε πελάτες μένουν όπως είναι."><span class="sr-only">Διαγραφή</span></x-ui.button>
                </div>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-zinc-500">Δεν υπάρχουν πακέτα ακόμα. Φτιάξε το πρώτο, π.χ. «10 συνεδρίες personal».</p>
        @endforelse
    </x-ui.card>

    @if ($showForm)
        <x-ui.modal :title="$form->package ? 'Επεξεργασία πακέτου' : 'Νέο πακέτο'" close="closeForm">
            <form id="package-form" wire:submit="save" class="space-y-4">
                <x-ui.input wire:model="form.name" label="Όνομα" placeholder="π.χ. 10 συνεδρίες personal" required />
                <x-ui.select wire:model="form.service_id" label="Ισχύει για" placeholder="Όλα τα προγράμματα">
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </x-ui.select>
                <div class="grid grid-cols-3 gap-3">
                    <x-ui.input wire:model="form.sessions_count" label="Συνεδρίες" type="number" min="1" required />
                    <x-ui.input wire:model="form.valid_days" label="Ισχύς (μέρες)" type="number" min="1" hint="Κενό = χωρίς λήξη" />
                    <x-ui.input wire:model="form.price" label="Τιμή (€)" type="number" min="0" step="0.01" required />
                </div>
                <x-ui.checkbox wire:model="form.is_active" label="Διαθέσιμο για νέους πελάτες" />
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeForm">Άκυρο</x-ui.button>
                <x-ui.button type="submit" form="package-form">Αποθήκευση</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
