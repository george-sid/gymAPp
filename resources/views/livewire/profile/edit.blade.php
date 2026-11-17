<div class="max-w-2xl space-y-6">
    <x-ui.card title="Στοιχεία λογαριασμού">
        <form wire:submit="updateDetails" class="space-y-4">
            <x-ui.input wire:model="name" label="Ονοματεπώνυμο" autocomplete="name" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="email" label="Email (για σύνδεση)" type="email" autocomplete="email" required />
                <x-ui.input wire:model="phone" label="Τηλέφωνο" type="tel" autocomplete="tel" />
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit">Αποθήκευση</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Αλλαγή κωδικού">
        <form wire:submit="updatePassword" class="space-y-4">
            <x-ui.input wire:model="current_password" label="Τρέχων κωδικός" type="password" autocomplete="current-password" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="password" label="Νέος κωδικός" type="password" autocomplete="new-password" hint="Τουλάχιστον 8 χαρακτήρες." required />
                <x-ui.input wire:model="password_confirmation" label="Επανάληψη νέου κωδικού" type="password" autocomplete="new-password" required />
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit">Αλλαγή κωδικού</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
