<div class="grid gap-6 xl:grid-cols-5">
    <x-ui.card title="Εβδομαδιαίο ωράριο" class="xl:col-span-3">
        <x-slot:actions>
            <x-ui.button size="sm" wire:click="saveSchedule">Αποθήκευση</x-ui.button>
        </x-slot:actions>

        <p class="mb-4 text-sm text-zinc-500">Οι ώρες που δουλεύεις κάθε εβδομάδα (ώρα Ελλάδας). Από αυτές βγαίνουν τα ελεύθερα slots.</p>

        <div class="divide-y divide-zinc-100">
            @foreach ($weekdays as $weekday => $label)
                <div wire:key="day-{{ $weekday }}" class="flex flex-col gap-3 py-3 sm:flex-row sm:items-start">
                    <div class="w-28 shrink-0 pt-2 text-sm font-medium">{{ $label }}</div>

                    <div class="flex-1 space-y-2">
                        @forelse ($schedule[$weekday] as $index => $range)
                            <div wire:key="day-{{ $weekday }}-range-{{ $index }}" class="flex flex-wrap items-center gap-2">
                                <input type="time" wire:model="schedule.{{ $weekday }}.{{ $index }}.start" class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none" aria-label="Έναρξη" />
                                <span class="text-zinc-400">–</span>
                                <input type="time" wire:model="schedule.{{ $weekday }}.{{ $index }}.end" class="rounded-lg border border-zinc-300 px-2 py-1.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none" aria-label="Λήξη" />
                                <button type="button" wire:click="removeRange({{ $weekday }}, {{ $index }})" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-red-600">
                                    <x-icon name="x" class="size-4" />
                                    <span class="sr-only">Αφαίρεση</span>
                                </button>
                                @error("schedule.{$weekday}.{$index}.start") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                                @error("schedule.{$weekday}.{$index}.end") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @empty
                            <p class="pt-2 text-sm text-zinc-400">Κλειστό</p>
                        @endforelse

                        @error("schedule.{$weekday}") <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <div class="flex flex-wrap gap-2">
                            <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="addRange({{ $weekday }})">Ώρες</x-ui.button>
                            @if ($schedule[$weekday] !== [])
                                <x-ui.button variant="ghost" size="sm" wire:click="copyToWeekdays({{ $weekday }})">Ίδιο ωράριο Δευ–Παρ</x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <div class="space-y-6 xl:col-span-2">
        <x-ui.card title="Νέα εξαίρεση">
            <form wire:submit="addException" class="space-y-4">
                <x-ui.select wire:model.live="exceptionType" label="Τύπος">
                    @foreach ($exceptionTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-ui.select>

                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input wire:model="exceptionStartsOn" label="Από" type="date" required />
                    <x-ui.input wire:model="exceptionEndsOn" label="Έως" type="date" hint="Κενό = μία μέρα" />
                </div>

                @if ($exceptionType === 'closed')
                    <x-ui.checkbox wire:model.live="exceptionAllDay" label="Όλη μέρα" />
                @endif

                @if (! $exceptionAllDay || $exceptionType === 'extra')
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input wire:model="exceptionStartTime" label="Ώρα έναρξης" type="time" />
                        <x-ui.input wire:model="exceptionEndTime" label="Ώρα λήξης" type="time" />
                    </div>
                @endif

                <x-ui.input wire:model="exceptionReason" label="Αιτία" placeholder="π.χ. Διακοπές, Αργία" />

                <div class="flex justify-end">
                    <x-ui.button type="submit" icon="plus">Προσθήκη</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Επερχόμενες εξαιρέσεις" :padding="false">
            @forelse ($this->exceptions as $exception)
                <div wire:key="exception-{{ $exception->id }}" class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0">
                    <div class="text-sm">
                        <div class="flex items-center gap-2 font-medium">
                            <x-ui.badge :color="$exception->type === \App\Enums\AvailabilityExceptionType::Closed ? 'red' : 'emerald'">{{ $exception->type->label() }}</x-ui.badge>
                            {{ $exception->starts_on->format('d/m/Y') }}
                            @unless ($exception->starts_on->equalTo($exception->ends_on))
                                – {{ $exception->ends_on->format('d/m/Y') }}
                            @endunless
                        </div>
                        <div class="mt-1 text-zinc-500">
                            {{ $exception->isAllDay() ? 'Όλη μέρα' : substr($exception->start_time, 0, 5).'–'.substr($exception->end_time, 0, 5) }}
                            @if ($exception->reason) · {{ $exception->reason }} @endif
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="deleteException({{ $exception->id }})"
                        wire:confirm="Να διαγραφεί η εξαίρεση;"
                        class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-red-600"
                    >
                        <x-icon name="trash" class="size-4" />
                        <span class="sr-only">Διαγραφή</span>
                    </button>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-zinc-500">Καμία εξαίρεση.</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
