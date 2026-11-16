<form wire:submit="save" class="max-w-3xl space-y-6">
    <x-ui.card title="Κανόνες κράτησης">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input wire:model="buffer_minutes" type="number" min="0" label="Διάλειμμα ανάμεσα σε ραντεβού (λεπτά)" />
            <x-ui.select wire:model="slot_interval_minutes" label="Τα slots ξεκινούν κάθε">
                @foreach ([5, 10, 15, 20, 30, 60] as $minutes)
                    <option value="{{ $minutes }}">{{ $minutes }} λεπτά</option>
                @endforeach
            </x-ui.select>
            <x-ui.input wire:model="min_notice_hours" type="number" min="0" label="Ελάχιστος χρόνος πριν την κράτηση (ώρες)" />
            <x-ui.input wire:model="max_advance_days" type="number" min="1" label="Κρατήσεις έως (μέρες μπροστά)" />
            <x-ui.input wire:model="free_cancellation_hours" type="number" min="0" label="Δωρεάν ακύρωση έως (ώρες πριν)" hint="Αργότερα ακύρωση ή no-show χρεώνεται τη συνεδρία." />
            <x-ui.input wire:model="reminder_hours_before" type="number" min="1" label="Υπενθύμιση με email (ώρες πριν)" />
        </div>
    </x-ui.card>

    <x-ui.card title="Στοιχεία γυμναστηρίου">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input wire:model="gym_name" label="Όνομα" class="sm:col-span-2" />
            <x-ui.input wire:model="gym_address" label="Διεύθυνση" class="sm:col-span-2" />
            <x-ui.input wire:model="gym_phone" type="tel" label="Τηλέφωνο" />
            <x-ui.input wire:model="gym_email" type="email" label="Email" />
        </div>
    </x-ui.card>

    <div class="flex justify-end">
        <x-ui.button type="submit">Αποθήκευση</x-ui.button>
    </div>
</form>
