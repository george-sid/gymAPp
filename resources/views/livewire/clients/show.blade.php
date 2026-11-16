@php
    $profile = $client->clientProfile;
    $tabs = ['details' => 'Στοιχεία'];
@endphp

<div>
    <x-ui.page-header>
        <div class="flex items-center gap-3">
            <x-ui.button variant="ghost" size="sm" icon="arrow-left" :href="route('clients.index')">Πελάτες</x-ui.button>
            @unless ($client->is_active)
                <x-ui.badge>Ανενεργός</x-ui.badge>
            @endunless
        </div>

        <x-slot:actions>
            <x-ui.button variant="secondary" icon="pencil" :href="route('clients.edit', $client)">Επεξεργασία</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                wire:key="tab-{{ $key }}"
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    '-mb-px border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap',
                    'border-emerald-600 text-emerald-700' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'details')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="Επικοινωνία" class="lg:col-span-1">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-zinc-500">Τηλέφωνο</dt>
                        <dd class="font-medium">
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}" class="hover:text-emerald-700">{{ $client->phone }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Email</dt>
                        <dd class="font-medium break-all">{{ $client->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Ηλικία</dt>
                        <dd class="font-medium">
                            {{ $profile?->birth_date ? $profile->birth_date->age.' ('.$profile->birth_date->format('d/m/Y').')' : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Φύλο</dt>
                        <dd class="font-medium">{{ $profile?->gender?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Πελάτης από</dt>
                        <dd class="font-medium">{{ $client->created_at->format('d/m/Y') }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="space-y-6 lg:col-span-2">
                <x-ui.card title="Στόχοι">
                    <p class="text-sm whitespace-pre-line text-zinc-700">{{ $profile?->goals ?: 'Δεν έχουν καταγραφεί στόχοι.' }}</p>
                </x-ui.card>

                <x-ui.card title="Ιατρικές σημειώσεις / τραυματισμοί">
                    <p class="text-sm whitespace-pre-line text-zinc-700">{{ $profile?->medical_notes ?: 'Καμία σημείωση.' }}</p>
                </x-ui.card>
            </div>
        </div>
    @endif
</div>
