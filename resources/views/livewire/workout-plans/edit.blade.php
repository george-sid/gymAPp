<div class="max-w-5xl space-y-6">
    <x-ui.page-header>
        <div class="flex flex-wrap items-center gap-3">
            @if ($plan->isTemplate())
                <x-ui.button variant="ghost" size="sm" icon="arrow-left" :href="route('workout-plans.index')">Πρότυπα</x-ui.button>
                <x-ui.badge>Πρότυπο</x-ui.badge>
            @else
                <x-ui.button variant="ghost" size="sm" icon="arrow-left" :href="route('clients.show', ['client' => $plan->client_id, 'tab' => 'workouts'])">{{ $plan->loadMissing('client')->client->name }}</x-ui.button>
                @if ($plan->is_active)
                    <x-ui.badge color="emerald">Ενεργό</x-ui.badge>
                @endif
            @endif
        </div>
    </x-ui.page-header>

    <x-ui.card title="Στοιχεία">
        <form wire:submit="saveDetails" class="space-y-4">
            <x-ui.input wire:model="name" label="Όνομα" required />
            <x-ui.textarea wire:model="description" label="Περιγραφή / οδηγίες" rows="2" />
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="secondary" size="sm">Αποθήκευση</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @if ($plan->isTemplate())
        <x-ui.card title="Ανάθεση σε πελάτη">
            <form wire:submit="assign" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                <x-ui.select wire:model="assignClientId" placeholder="— Επίλεξε πελάτη —" class="flex-1" aria-label="Πελάτης">
                    @foreach ($this->clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit">Ανάθεση</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    @foreach ($this->days as $day)
        <section wire:key="day-{{ $day->id }}" class="rounded-xl border border-zinc-200 bg-white shadow-xs">
            <header class="flex items-center gap-3 border-b border-zinc-200 px-5 py-3">
                <input
                    type="text"
                    wire:model.blur="dayNames.{{ $day->id }}"
                    class="min-w-0 flex-1 rounded-md border border-transparent px-2 py-1 font-semibold hover:border-zinc-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 focus:outline-none"
                    aria-label="Όνομα ημέρας"
                />
                <button type="button" wire:click="deleteDay({{ $day->id }})" wire:confirm="Να διαγραφεί η «{{ $day->name }}» με τις ασκήσεις της;" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-red-600">
                    <x-icon name="trash" class="size-4" />
                    <span class="sr-only">Διαγραφή ημέρας</span>
                </button>
            </header>
            @error("dayNames.{$day->id}") <p class="px-5 pt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            @if ($day->exercises->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                            <tr>
                                <th class="px-5 py-2">Άσκηση</th>
                                <th class="w-16 px-2 py-2">Σετ</th>
                                <th class="w-24 px-2 py-2">Επαν.</th>
                                <th class="w-20 px-2 py-2">Κιλά</th>
                                <th class="w-24 px-2 py-2">Διάλ. (″)</th>
                                <th class="min-w-40 px-2 py-2">Σημείωση</th>
                                <th class="w-28 px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($day->exercises as $planExercise)
                                <tr wire:key="plan-exercise-{{ $planExercise->id }}">
                                    <td class="px-5 py-2 font-medium">
                                        {{ $planExercise->exercise->name }}
                                        @if ($planExercise->exercise->video_url)
                                            <a href="{{ $planExercise->exercise->video_url }}" target="_blank" rel="noopener" class="ml-1 text-xs font-normal text-emerald-700 hover:underline">βίντεο</a>
                                        @endif
                                    </td>
                                    @foreach (['sets' => 'number', 'reps' => 'text', 'weight' => 'number', 'rest_sec' => 'number', 'notes' => 'text'] as $field => $type)
                                        <td class="px-2 py-2">
                                            <input
                                                type="{{ $type }}"
                                                @if ($field === 'weight') step="0.5" min="0" @elseif ($type === 'number') min="0" @endif
                                                wire:model.blur="fields.{{ $planExercise->id }}.{{ $field }}"
                                                @class([
                                                    'w-full rounded-md border px-2 py-1 text-sm focus:ring-2 focus:outline-none',
                                                    'border-red-400 focus:ring-red-200' => $errors->has("fields.{$planExercise->id}.{$field}"),
                                                    'border-zinc-200 focus:border-emerald-500 focus:ring-emerald-200' => ! $errors->has("fields.{$planExercise->id}.{$field}"),
                                                ])
                                            />
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2">
                                        <div class="flex justify-end gap-0.5 text-zinc-400">
                                            <button type="button" wire:click="moveExercise({{ $planExercise->id }}, 'up')" class="rounded p-1 hover:bg-zinc-100 hover:text-zinc-700" @disabled($loop->first)>
                                                <x-icon name="chevron-left" class="size-4 rotate-90" /><span class="sr-only">Πάνω</span>
                                            </button>
                                            <button type="button" wire:click="moveExercise({{ $planExercise->id }}, 'down')" class="rounded p-1 hover:bg-zinc-100 hover:text-zinc-700" @disabled($loop->last)>
                                                <x-icon name="chevron-right" class="size-4 rotate-90" /><span class="sr-only">Κάτω</span>
                                            </button>
                                            <button type="button" wire:click="removeExercise({{ $planExercise->id }})" class="rounded p-1 hover:bg-zinc-100 hover:text-red-600">
                                                <x-icon name="x" class="size-4" /><span class="sr-only">Αφαίρεση</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @foreach ($errors->getMessages() as $key => $messages)
                    @if (str_starts_with($key, 'fields.') && $day->exercises->contains('id', (int) explode('.', $key)[1]))
                        <p class="px-5 text-sm text-red-600">{{ $messages[0] }}</p>
                    @endif
                @endforeach
            @endif

            <form wire:submit="addExercise({{ $day->id }})" class="flex flex-col gap-2 border-t border-zinc-100 px-5 py-3 sm:flex-row sm:items-start">
                <x-ui.select wire:model="newExercise.{{ $day->id }}" placeholder="— Πρόσθεσε άσκηση —" class="flex-1" aria-label="Άσκηση">
                    @foreach ($this->exerciseOptions->groupBy(fn ($exercise) => $exercise->muscle_group->label()) as $group => $options)
                        <optgroup label="{{ $group }}">
                            @foreach ($options as $exercise)
                                <option value="{{ $exercise->id }}">{{ $exercise->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" variant="secondary" icon="plus">Προσθήκη</x-ui.button>
            </form>
        </section>
    @endforeach

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button variant="secondary" icon="plus" wire:click="addDay">Νέα ημέρα</x-ui.button>
        @if ($this->exerciseOptions->isEmpty())
            <p class="text-sm text-zinc-500">Η βιβλιοθήκη ασκήσεων είναι άδεια. <a href="{{ route('exercises.index') }}" wire:navigate class="font-medium text-emerald-700 underline">Πρόσθεσε ασκήσεις</a>.</p>
        @endif
    </div>
</div>
