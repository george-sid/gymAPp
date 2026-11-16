<div>
    <x-ui.page-header>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500">
            <span>Σύρε σε κενό χώρο για νέο ραντεβού, μετακίνησε ή άλλαξε διάρκεια με drag & drop.</span>
            <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-red-200"></span> Κλειστό</span>
        </div>

        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="openCreate('{{ \App\Support\LocalTime::now()->addHour()->startOfHour()->format('Y-m-d\TH:i:s') }}')">Νέο</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div
        wire:ignore
        x-data="calendar(@js($calendarConfig))"
        x-on:calendar-refresh.window="refresh()"
        class="rounded-xl border border-zinc-200 bg-white p-2 shadow-xs sm:p-4"
    >
        <div x-ref="calendar"></div>
    </div>

    {{-- Create / edit modal --}}
    @if ($showCreate)
        <x-ui.modal :title="$editingEventId ? 'Επεξεργασία' : 'Νέο στο ημερολόγιο'" close="closeCreate">
            @unless ($editingEventId)
                <div class="mb-4 grid grid-cols-2 gap-1 rounded-lg bg-zinc-100 p-1 text-sm font-medium">
                    <button type="button" wire:click="$set('createMode', 'session')" @class(['rounded-md px-3 py-1.5', 'bg-white shadow-xs' => $createMode === 'session', 'text-zinc-500' => $createMode !== 'session'])>Ραντεβού</button>
                    <button type="button" wire:click="$set('createMode', 'event')" @class(['rounded-md px-3 py-1.5', 'bg-white shadow-xs' => $createMode === 'event', 'text-zinc-500' => $createMode !== 'event'])>Προσωπικό / μπλοκάρισμα</button>
                </div>
            @endunless

            @if ($createMode === 'session')
                @if ($this->services->isEmpty())
                    <p class="text-sm text-zinc-600">
                        Φτιάξε πρώτα ένα <a href="{{ route('services.index') }}" wire:navigate class="font-medium text-emerald-700 underline">πρόγραμμα</a> (π.χ. Personal 1:1).
                    </p>
                @else
                    <form id="session-form" wire:submit="saveSession" class="space-y-4">
                        <x-ui.select wire:model.live="serviceId" label="Πρόγραμμα">
                            @foreach ($this->services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_min }}′{{ $service->isGroup() ? ' · έως '.$service->capacity.' άτομα' : '' }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select wire:model="clientId" label="Πελάτης" placeholder="— Χωρίς πελάτη (π.χ. ομαδικό, προσθέτεις μετά) —">
                            @foreach ($this->clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <div class="grid grid-cols-2 gap-3">
                            <x-ui.input wire:model.live="date" label="Ημερομηνία" type="date" />
                            <x-ui.input wire:model="time" label="Ώρα" type="time" step="300" />
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium text-zinc-700">Ελεύθερες ώρες</p>
                            @if ($this->availableSlots->isEmpty())
                                <p class="text-sm text-zinc-500">Καμία ελεύθερη ώρα στο ωράριο αυτής της μέρας. Μπορείς να γράψεις ώρα και να κλείσεις εκτός ωραρίου.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->availableSlots as $slot)
                                        <button
                                            type="button"
                                            wire:key="slot-{{ $slot }}"
                                            wire:click="pickSlot('{{ $slot }}')"
                                            @class([
                                                'rounded-lg border px-2.5 py-1 text-sm',
                                                'border-emerald-600 bg-emerald-600 text-white' => $time === $slot,
                                                'border-zinc-300 hover:border-emerald-500' => $time !== $slot,
                                            ])
                                        >{{ $slot }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <x-ui.select wire:model="weeks" label="Επανάληψη">
                            <option value="1">Μία φορά</option>
                            @foreach ([2, 4, 6, 8, 10, 12, 16, 24] as $count)
                                <option value="{{ $count }}">Κάθε εβδομάδα, για {{ $count }} εβδομάδες</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.textarea wire:model="notes" label="Σημειώσεις" rows="2" />

                        @if ($skipped !== [])
                            <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                <p class="font-medium">Δεν κλείστηκαν:</p>
                                <ul class="mt-1 list-disc pl-5">
                                    @foreach ($skipped as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </form>
                @endif
            @else
                <form id="event-form" wire:submit="saveEvent" class="space-y-4">
                    <x-ui.input wire:model="eventTitle" label="Τίτλος" placeholder="π.χ. Γιατρός, Διάλειμμα" />
                    <x-ui.select wire:model="eventType" label="Τύπος">
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="date" label="Ημερομηνία" type="date" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input wire:model="eventStart" label="Από" type="time" step="300" />
                        <x-ui.input wire:model="eventEnd" label="Έως" type="time" step="300" />
                    </div>
                    <x-ui.textarea wire:model="notes" label="Σημειώσεις" rows="2" />
                </form>
            @endif

            <x-slot:footer>
                @if ($editingEventId)
                    <x-ui.button variant="ghost" class="mr-auto text-red-600 hover:bg-red-50" wire:click="deleteEvent" wire:confirm="Να διαγραφεί;">Διαγραφή</x-ui.button>
                @endif
                <x-ui.button variant="secondary" wire:click="closeCreate">{{ $skipped !== [] ? 'Κλείσιμο' : 'Άκυρο' }}</x-ui.button>
                @if ($createMode === 'event')
                    <x-ui.button type="submit" form="event-form">Αποθήκευση</x-ui.button>
                @elseif ($this->services->isNotEmpty() && $skipped === [])
                    <x-ui.button type="submit" form="session-form" wire:loading.attr="disabled" wire:target="saveSession">Κλείσιμο ραντεβού</x-ui.button>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Session details modal --}}
    @if ($session = $this->selectedSession)
        <x-ui.modal :title="$session->service->name" close="closeSession">
            <div class="space-y-5">
                <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-600">
                    <x-icon name="calendar" class="size-4" />
                    {{ $session->localStartsAt()->translatedFormat('l j F Y') }},
                    {{ $session->localStartsAt()->format('H:i') }}–{{ $session->localEndsAt()->format('H:i') }}
                    @if ($session->isCancelled())
                        <x-ui.badge color="red">Ακυρώθηκε</x-ui.badge>
                    @endif
                    @if ($session->recurrence_id)
                        <x-ui.badge>Σταθερό ραντεβού</x-ui.badge>
                    @endif
                </div>

                @if ($session->notes)
                    <p class="rounded-lg bg-zinc-50 p-3 text-sm whitespace-pre-line text-zinc-700">{{ $session->notes }}</p>
                @endif

                <div>
                    @php($activeCount = $session->bookings->filter(fn ($booking) => $booking->status->occupiesPlace())->count())
                    <h3 class="mb-2 text-sm font-semibold">Συμμετέχοντες ({{ $activeCount }}/{{ $session->capacity }})</h3>

                    @forelse ($session->bookings as $booking)
                        <div wire:key="booking-{{ $booking->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 py-2 last:border-0">
                            <a href="{{ route('clients.show', $booking->client) }}" wire:navigate @class(['text-sm font-medium hover:text-emerald-700', 'text-zinc-400 line-through' => ! $booking->status->occupiesPlace()])>
                                {{ $booking->client->name }}
                            </a>
                            <select
                                wire:change="setBookingStatus({{ $booking->id }}, $event.target.value)"
                                class="rounded-lg border border-zinc-300 py-1 pr-8 pl-2 text-sm"
                                aria-label="Κατάσταση κράτησης"
                            >
                                @foreach ($bookingStatuses as $status)
                                    <option value="{{ $status->value }}" @selected($booking->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500">Κανείς ακόμα.</p>
                    @endforelse
                </div>

                @if (! $session->isCancelled() && $activeCount < $session->capacity)
                    <form wire:submit="addClientToSession" class="flex items-end gap-2">
                        <x-ui.select wire:model="addClientId" label="Προσθήκη πελάτη" placeholder="— Επίλεξε —" class="flex-1">
                            @foreach ($this->clients->whereNotIn('id', $session->bookings->filter(fn ($booking) => $booking->status->occupiesPlace())->pluck('client_id')) as $client)
                                <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="submit" icon="plus">Προσθήκη</x-ui.button>
                    </form>
                @endif
            </div>

            <x-slot:footer>
                @unless ($session->isCancelled())
                    <x-ui.button
                        variant="ghost"
                        class="mr-auto text-red-600 hover:bg-red-50"
                        wire:click="cancelSession"
                        wire:confirm="Να ακυρωθεί η συνεδρία και όλες οι κρατήσεις της;"
                    >Ακύρωση συνεδρίας</x-ui.button>
                @endunless
                <x-ui.button variant="secondary" wire:click="closeSession">Κλείσιμο</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
