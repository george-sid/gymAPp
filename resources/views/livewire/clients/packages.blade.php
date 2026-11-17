@php
    $money = fn ($value) => \Illuminate\Support\Number::currency((float) $value, 'EUR', 'el');
    $account = $this->account;
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="text-sm text-zinc-500">Διαθέσιμες συνεδρίες</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $account['available_sessions'] }}</div>
            <div class="text-xs text-zinc-500">σε ενεργά πακέτα</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="text-sm text-zinc-500">Κλεισμένα ραντεβού</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $account['upcoming_bookings'] }}</div>
            <div @class(['text-xs', 'font-medium text-amber-700' => $account['free_sessions'] < 0, 'text-zinc-500' => $account['free_sessions'] >= 0])>
                {{ $account['free_sessions'] < 0 ? 'λείπουν '.abs($account['free_sessions']).' συνεδρίες' : 'καλύπτονται από πακέτο' }}
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="text-sm text-zinc-500">Χρωστάει συνεδρίες</div>
            <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-600' => $account['owed_sessions'] > 0])>{{ $account['owed_sessions'] }}</div>
            <div class="text-xs text-zinc-500">καλύπτονται από το επόμενο πακέτο</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4">
            <div class="text-sm text-zinc-500">Υπόλοιπο ποσό</div>
            <div @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-600' => $account['amount_due'] > 0])>{{ $money(max(0, $account['amount_due'])) }}</div>
            <div class="text-xs text-zinc-500">{{ $account['amount_due'] < 0 ? 'προπληρωμή '.$money(abs($account['amount_due'])) : 'πακέτα μείον πληρωμές' }}</div>
        </div>
    </div>

    <x-ui.card title="Πακέτα" :padding="false">
        <x-slot:actions>
            <x-ui.button size="sm" variant="secondary" icon="credit-card" wire:click="openPayment">Πληρωμή</x-ui.button>
            <x-ui.button size="sm" icon="plus" wire:click="openAssign">Νέο πακέτο</x-ui.button>
        </x-slot:actions>

        @forelse ($this->clientPackages as $clientPackage)
            @php
                $used = $clientPackage->sessionsUsed();
                $expired = $clientPackage->isExpired();
                $finished = $clientPackage->remainingSessions() === 0;
            @endphp
            <div wire:key="client-package-{{ $clientPackage->id }}" class="border-b border-zinc-100 px-5 py-4 last:border-0">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 font-medium">
                            {{ $clientPackage->name }}
                            @if ($expired)
                                <x-ui.badge color="red">Έληξε</x-ui.badge>
                            @elseif ($finished)
                                <x-ui.badge>Ολοκληρώθηκε</x-ui.badge>
                            @else
                                <x-ui.badge color="emerald">Ενεργό</x-ui.badge>
                            @endif
                        </div>
                        <div class="text-sm text-zinc-500">
                            Από {{ $clientPackage->starts_on->format('d/m/Y') }}
                            · {{ $clientPackage->expires_on ? 'λήγει '.$clientPackage->expires_on->format('d/m/Y') : 'χωρίς λήξη' }}
                        </div>
                    </div>
                    <div class="text-right text-sm">
                        <div class="font-medium tabular-nums">{{ $money($clientPackage->price) }}</div>
                        @if ($clientPackage->amountDue() > 0)
                            <button type="button" wire:click="openPayment({{ $clientPackage->id }})" class="text-red-600 hover:underline">
                                χρωστάει {{ $money($clientPackage->amountDue()) }}
                            </button>
                        @else
                            <div class="text-emerald-700">εξοφλημένο</div>
                        @endif
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $clientPackage->sessions_total }}" aria-valuenow="{{ $used }}">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ min(100, $used / max(1, $clientPackage->sessions_total) * 100) }}%"></div>
                    </div>
                    <span class="text-sm text-zinc-600 tabular-nums">{{ $used }}/{{ $clientPackage->sessions_total }} συνεδρίες</span>
                </div>

                @if ($clientPackage->notes)
                    <p class="mt-2 text-sm text-zinc-500">{{ $clientPackage->notes }}</p>
                @endif
            </div>
        @empty
            <p class="px-5 py-6 text-center text-sm text-zinc-500">Δεν έχει πακέτο ακόμα.</p>
        @endforelse
    </x-ui.card>

    <x-ui.card title="Πληρωμές" :padding="false">
        @if ($this->payments->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-zinc-500">Καμία πληρωμή.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-medium tracking-wide text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-3">Ημερομηνία</th>
                            <th class="px-3 py-3 text-right">Ποσό</th>
                            <th class="px-3 py-3">Τρόπος</th>
                            <th class="px-3 py-3">Απόδειξη</th>
                            <th class="px-3 py-3">Πακέτο</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->payments as $payment)
                            <tr wire:key="payment-{{ $payment->id }}">
                                <td class="px-5 py-2 whitespace-nowrap">{{ $payment->paid_on->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $money($payment->amount) }}</td>
                                <td class="px-3 py-2">{{ $payment->method->label() }}</td>
                                <td class="px-3 py-2 text-zinc-600">{{ $payment->receipt_number ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-600">{{ $payment->clientPackage?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" wire:click="deletePayment({{ $payment->id }})" wire:confirm="Να διαγραφεί η πληρωμή των {{ $money($payment->amount) }};" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-red-600">
                                        <x-icon name="trash" class="size-4" /><span class="sr-only">Διαγραφή</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($showAssign)
        <x-ui.modal title="Νέο πακέτο" close="closeModals">
            @if ($this->packageTemplates->isEmpty())
                <p class="text-sm text-zinc-600">Φτιάξε πρώτα ένα <a href="{{ route('packages.index') }}" wire:navigate class="font-medium text-emerald-700 underline">πακέτο</a>.</p>
            @else
                <form id="assign-package-form" wire:submit="assign" class="space-y-4">
                    <x-ui.select wire:model.live="packageId" label="Πακέτο">
                        @foreach ($this->packageTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} · {{ $template->sessions_count }} συνεδρίες · {{ $money($template->price) }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input wire:model="startsOn" label="Έναρξη" type="date" />
                        <x-ui.input wire:model="price" label="Τιμή (€)" type="number" min="0" step="0.01" hint="Άλλαξέ την για έκπτωση." />
                    </div>
                    <x-ui.textarea wire:model="packageNotes" label="Σημειώσεις" rows="2" />
                    @if ($account['owed_sessions'] > 0)
                        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Οι {{ $account['owed_sessions'] }} συνεδρίες που χρωστάει θα αφαιρεθούν πρώτες από αυτό το πακέτο.</p>
                    @endif
                </form>
            @endif

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModals">Άκυρο</x-ui.button>
                @if ($this->packageTemplates->isNotEmpty())
                    <x-ui.button type="submit" form="assign-package-form">Προσθήκη</x-ui.button>
                @endif
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @if ($showPayment)
        <x-ui.modal title="Καταγραφή πληρωμής" close="closeModals">
            <form id="payment-form" wire:submit="recordPayment" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input wire:model="amount" label="Ποσό (€)" type="number" min="0.01" step="0.01" required />
                    <x-ui.input wire:model="paidOn" label="Ημερομηνία" type="date" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.select wire:model="method" label="Τρόπος">
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->value }}">{{ $paymentMethod->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="receiptNumber" label="Αρ. απόδειξης" />
                </div>
                <x-ui.select wire:model="paymentPackageId" label="Για πακέτο" placeholder="— Χωρίς πακέτο —">
                    @foreach ($this->clientPackages as $clientPackage)
                        <option value="{{ $clientPackage->id }}">{{ $clientPackage->name }} ({{ $clientPackage->starts_on->format('d/m/Y') }})</option>
                    @endforeach
                </x-ui.select>
                <x-ui.textarea wire:model="paymentNotes" label="Σημειώσεις" rows="2" />
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" wire:click="closeModals">Άκυρο</x-ui.button>
                <x-ui.button type="submit" form="payment-form">Καταγραφή</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
