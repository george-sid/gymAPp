<div>
    <x-ui.page-header>
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative sm:w-72">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Αναζήτηση ονόματος, email, τηλεφώνου"
                    class="block w-full rounded-lg border border-zinc-300 bg-white py-2 pr-3 pl-9 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none"
                />
            </div>
            <select wire:model.live="status" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none">
                <option value="active">Ενεργοί</option>
                <option value="inactive">Ανενεργοί</option>
                <option value="all">Όλοι</option>
            </select>
        </div>

        <x-slot:actions>
            <x-ui.button :href="route('clients.create')" icon="plus">Νέος πελάτης</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        @if ($this->clients->isEmpty())
            <p class="p-8 text-center text-sm text-zinc-500">
                {{ $search === '' ? 'Δεν υπάρχουν πελάτες ακόμα.' : 'Δεν βρέθηκαν πελάτες.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Όνομα</th>
                            <th class="px-5 py-3">Τηλέφωνο</th>
                            <th class="hidden px-5 py-3 md:table-cell">Email</th>
                            <th class="hidden px-5 py-3 sm:table-cell">Ηλικία</th>
                            <th class="px-5 py-3">Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->clients as $client)
                            <tr wire:key="client-{{ $client->id }}" class="hover:bg-zinc-50">
                                <td class="px-5 py-3 font-medium">
                                    <a href="{{ route('clients.show', $client) }}" wire:navigate class="hover:text-emerald-700">{{ $client->name }}</a>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-zinc-600">{{ $client->phone ?? '—' }}</td>
                                <td class="hidden px-5 py-3 text-zinc-600 md:table-cell">{{ $client->email ?? '—' }}</td>
                                <td class="hidden px-5 py-3 text-zinc-600 sm:table-cell">{{ $client->clientProfile?->birth_date?->age ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($client->is_active)
                                        <x-ui.badge color="emerald">Ενεργός</x-ui.badge>
                                    @else
                                        <x-ui.badge>Ανενεργός</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->clients->hasPages())
                <div class="border-t border-zinc-200 px-5 py-3">
                    {{ $this->clients->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
