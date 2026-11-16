<div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
    <h2 class="mb-6 text-lg font-semibold">Σύνδεση στο admin</h2>

    <form wire:submit="login" class="space-y-4">
        <x-ui.input wire:model="email" label="Email" type="email" autocomplete="username" autofocus required />
        <x-ui.input wire:model="password" label="Κωδικός" type="password" autocomplete="current-password" required />
        <x-ui.checkbox wire:model="remember" label="Να με θυμάσαι" />

        <x-ui.button type="submit" class="w-full">
            <span wire:loading.remove wire:target="login">Σύνδεση</span>
            <span wire:loading wire:target="login">Σύνδεση…</span>
        </x-ui.button>
    </form>
</div>
