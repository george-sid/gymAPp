@php
    $profile = $client->clientProfile;
    $tabs = ['details' => 'Στοιχεία', 'bookings' => 'Ραντεβού', 'workouts' => 'Προπόνηση'];
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

    @if ($tab === 'bookings')
        <div class="space-y-6">
            <div class="grid grid-cols-3 gap-3 sm:max-w-md">
                @foreach ([['completed', 'Παρουσίες', 'text-emerald-700'], ['no_show', 'No-show', 'text-red-600'], ['cancelled', 'Ακυρώσεις', 'text-zinc-600']] as [$status, $label, $color])
                    <div class="rounded-xl border border-zinc-200 bg-white p-3 text-center">
                        <div class="text-2xl font-semibold {{ $color }}">{{ $this->attendance[$status] ?? 0 }}</div>
                        <div class="text-xs text-zinc-500">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <x-ui.card title="Επερχόμενα" :padding="false">
                @forelse ($this->upcomingBookings as $booking)
                    <div wire:key="upcoming-{{ $booking->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 text-sm last:border-0">
                        <div>
                            <div class="font-medium">{{ $booking->trainingSession->localStartsAt()->translatedFormat('D j M, H:i') }}</div>
                            <div class="text-zinc-500">{{ $booking->trainingSession->service->name }}</div>
                        </div>
                        <x-ui.badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-ui.badge>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-zinc-500">Κανένα επερχόμενο ραντεβού.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="Ιστορικό" :padding="false">
                @forelse ($this->pastBookings as $booking)
                    <div wire:key="past-{{ $booking->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 text-sm last:border-0">
                        <div>
                            <div class="font-medium">{{ $booking->trainingSession->localStartsAt()->translatedFormat('D j M Y, H:i') }}</div>
                            <div class="text-zinc-500">{{ $booking->trainingSession->service->name }}</div>
                        </div>
                        <x-ui.badge :color="$booking->status->color()">{{ $booking->status->label() }}</x-ui.badge>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-zinc-500">Δεν υπάρχει ιστορικό.</p>
                @endforelse
            </x-ui.card>
        </div>
    @endif

    @if ($tab === 'workouts')
        <div class="space-y-6">
            <x-ui.card title="Νέο πρόγραμμα προπόνησης">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    @if ($this->planTemplates->isNotEmpty())
                        <form wire:submit="assignTemplate" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-start">
                            <x-ui.select wire:model="templateId" placeholder="— Από πρότυπο —" class="flex-1" aria-label="Πρότυπο">
                                @foreach ($this->planTemplates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.button type="submit">Ανάθεση</x-ui.button>
                        </form>
                        <span class="self-center text-sm text-zinc-400">ή</span>
                    @endif
                    <x-ui.button variant="secondary" icon="plus" wire:click="createEmptyPlan">Κενό πρόγραμμα</x-ui.button>
                </div>
            </x-ui.card>

            <x-ui.card title="Προγράμματα" :padding="false">
                @forelse ($this->workoutPlans as $plan)
                    <div wire:key="workout-plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                        <a href="{{ route('workout-plans.edit', $plan) }}" wire:navigate class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 font-medium hover:text-emerald-700">
                                {{ $plan->name }}
                                @if ($plan->is_active)
                                    <x-ui.badge color="emerald">Ενεργό</x-ui.badge>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500">
                                {{ $plan->days_count }} {{ $plan->days_count === 1 ? 'ημέρα' : 'ημέρες' }}
                                @if ($plan->assigned_at) · από {{ $plan->assigned_at->format('d/m/Y') }} @endif
                            </div>
                        </a>
                        @unless ($plan->is_active)
                            <x-ui.button variant="ghost" size="sm" wire:click="activatePlan({{ $plan->id }})">Ενεργοποίηση</x-ui.button>
                        @endunless
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-zinc-500">Δεν έχει πρόγραμμα προπόνησης.</p>
                @endforelse
            </x-ui.card>
        </div>
    @endif
</div>
