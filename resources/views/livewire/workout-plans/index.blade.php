<div class="max-w-4xl space-y-6">
    <x-ui.card title="Νέο πρότυπο">
        <form wire:submit="createTemplate" class="flex flex-col gap-2 sm:flex-row sm:items-start">
            <x-ui.input wire:model="newName" placeholder="π.χ. Αρχάριοι Full Body 3×/εβδ" class="flex-1" aria-label="Όνομα" />
            <x-ui.button type="submit" icon="plus">Δημιουργία</x-ui.button>
        </form>
        <p class="mt-3 text-sm text-zinc-500">Τα πρότυπα τα αναθέτεις σε πελάτες από την καρτέλα τους. Ο πελάτης παίρνει δικό του αντίγραφο που μπορείς να προσαρμόσεις.</p>
    </x-ui.card>

    <x-ui.card title="Πρότυπα" :padding="false">
        @forelse ($this->templates as $plan)
            <div wire:key="plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                <a href="{{ route('workout-plans.edit', $plan) }}" wire:navigate class="min-w-0 flex-1">
                    <div class="font-medium hover:text-emerald-700">{{ $plan->name }}</div>
                    <div class="text-sm text-zinc-500">{{ $plan->days_count }} {{ $plan->days_count === 1 ? 'ημέρα' : 'ημέρες' }}</div>
                </a>
                <x-ui.button variant="ghost" size="sm" icon="trash" class="text-red-600 hover:bg-red-50" wire:click="deleteTemplate({{ $plan->id }})" wire:confirm="Να διαγραφεί το πρότυπο; Τα προγράμματα που έχουν ήδη ανατεθεί σε πελάτες μένουν."><span class="sr-only">Διαγραφή</span></x-ui.button>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-zinc-500">Δεν υπάρχουν πρότυπα ακόμα.</p>
        @endforelse
    </x-ui.card>
</div>
