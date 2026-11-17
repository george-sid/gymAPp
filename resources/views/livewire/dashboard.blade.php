<div class="space-y-6">
    <p class="text-sm text-zinc-500">{{ \Illuminate\Support\Str::ucfirst(\App\Support\LocalTime::now()->translatedFormat('l j F Y')) }}</p>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs">
            <div class="text-sm text-zinc-500">Έσοδα μήνα</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ \Illuminate\Support\Number::currency($this->revenueThisMonth, 'EUR', 'el') }}</div>
            <div class="text-xs text-zinc-500">πληρωμές {{ \App\Support\LocalTime::now()->translatedFormat('F') }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs">
            <div class="text-sm text-zinc-500">Αυτή την εβδομάδα</div>
            <div class="mt-1 text-2xl font-semibold">{{ $this->week['sessions'] }}</div>
            <div class="text-xs text-zinc-500">συνεδρίες</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs">
            <div class="text-sm text-zinc-500">Πληρότητα ωρών</div>
            <div class="mt-1 text-2xl font-semibold">{{ $this->week['occupancy'] !== null ? $this->week['occupancy'].'%' : '—' }}</div>
            <div class="text-xs text-zinc-500">
                @if ($this->week['occupancy'] !== null)
                    {{ round($this->week['booked_minutes'] / 60, 1) }} από {{ round($this->week['working_minutes'] / 60, 1) }} ώρες
                @else
                    <a href="{{ route('availability.index') }}" wire:navigate class="underline">όρισε ωράριο</a>
                @endif
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs">
            <div class="text-sm text-zinc-500">Ενεργοί πελάτες</div>
            <div class="mt-1 text-2xl font-semibold">{{ $this->activeClientsCount }}</div>
            <div class="text-xs text-zinc-500">{{ $this->cancellationsLastWeek }} ακυρώσεις τις τελευταίες 7 μέρες</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card title="Σημερινά ραντεβού" :padding="false" class="lg:col-span-3">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" :href="route('calendar')">Ημερολόγιο</x-ui.button>
            </x-slot:actions>

            @forelse ($this->todaySessions as $session)
                <div wire:key="today-{{ $session->id }}" class="flex items-start gap-4 border-b border-zinc-100 px-5 py-3 last:border-0">
                    <div class="w-14 shrink-0 pt-0.5 text-sm font-semibold tabular-nums">{{ $session->localStartsAt()->format('H:i') }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $session->service->color }}"></span>
                            {{ $session->service->name }}
                            @if ($session->capacity > 1)
                                <span class="text-zinc-500">({{ $session->activeBookings->count() }}/{{ $session->capacity }})</span>
                            @endif
                        </div>
                        <div class="mt-0.5 truncate text-sm text-zinc-600">
                            @forelse ($session->activeBookings as $booking)
                                <a href="{{ route('clients.show', $booking->client) }}" wire:navigate class="hover:text-emerald-700">{{ $booking->client->name }}</a>@if (! $loop->last), @endif
                            @empty
                                <span class="text-zinc-400">Χωρίς κράτηση</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="text-xs text-zinc-500 tabular-nums">έως {{ $session->localEndsAt()->format('H:i') }}</div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Δεν έχεις ραντεβού σήμερα.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Πρόσφατη κίνηση" :padding="false" class="lg:col-span-2">
            @forelse ($this->recentActivity as $change)
                @php($session = $change->booking->trainingSession)
                <div wire:key="activity-{{ $change->id }}" class="border-b border-zinc-100 px-5 py-3 text-sm last:border-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ $change->booking->client->name }}</span>
                        @if ($change->from_status === null)
                            <x-ui.badge color="sky">Νέα κράτηση</x-ui.badge>
                        @else
                            <x-ui.badge :color="$change->to_status->color()">{{ $change->to_status->label() }}</x-ui.badge>
                        @endif
                    </div>
                    <div class="mt-0.5 text-zinc-500">
                        {{ $session->service->name }} · {{ $session->localStartsAt()->translatedFormat('D j M, H:i') }}
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Καμία κίνηση τις τελευταίες 7 μέρες.</p>
            @endforelse
        </x-ui.card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Πακέτα που τελειώνουν" :padding="false">
            @forelse ($this->packagesRunningOut as $package)
                <a href="{{ route('clients.show', ['client' => $package->client_id, 'tab' => 'packages']) }}" wire:navigate wire:key="running-out-{{ $package->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 text-sm last:border-0 hover:bg-zinc-50">
                    <div class="min-w-0">
                        <div class="font-medium">{{ $package->client->name }}</div>
                        <div class="truncate text-zinc-500">{{ $package->name }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div @class(['font-medium tabular-nums', 'text-red-600' => $package->remainingSessions() === 0])>{{ $package->remainingSessions() }} {{ $package->remainingSessions() === 1 ? 'συνεδρία' : 'συνεδρίες' }}</div>
                        @if ($package->expires_on)
                            <div class="text-xs text-zinc-500">λήγει {{ $package->expires_on->format('d/m') }}</div>
                        @endif
                    </div>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Κανένα πακέτο δεν τελειώνει σύντομα.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Οφειλές" :padding="false">
            @forelse ($this->clientsOwing as $row)
                <a href="{{ route('clients.show', ['client' => $row['client'], 'tab' => 'packages']) }}" wire:navigate wire:key="owing-{{ $row['client']->id }}" class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 text-sm last:border-0 hover:bg-zinc-50">
                    <span class="font-medium">{{ $row['client']->name }}</span>
                    <span class="text-right">
                        @if ($row['amount_due'] > 0)
                            <span class="font-medium text-red-600 tabular-nums">{{ \Illuminate\Support\Number::currency($row['amount_due'], 'EUR', 'el') }}</span>
                        @endif
                        @if ($row['owed_sessions'] > 0)
                            <span class="block text-xs text-zinc-500">{{ $row['owed_sessions'] }} {{ $row['owed_sessions'] === 1 ? 'συνεδρία' : 'συνεδρίες' }} χωρίς πακέτο</span>
                        @endif
                    </span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-zinc-500">Κανείς δεν χρωστάει.</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
