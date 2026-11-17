<?php

namespace App\Livewire\Settings;

use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ρυθμίσεις')]
class Index extends Component
{
    public int|string $buffer_minutes = '';

    public int|string $slot_interval_minutes = '';

    public int|string $min_notice_hours = '';

    public int|string $max_advance_days = '';

    public int|string $free_cancellation_hours = '';

    public int|string $reminder_hours_before = '';

    public string $gym_name = '';

    public string $gym_address = '';

    public string $gym_phone = '';

    public string $gym_email = '';

    public bool $notify_clients = true;

    public function mount(Settings $settings): void
    {
        foreach ($settings->all() as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value ?? '';
            }
        }
    }

    public function save(Settings $settings): void
    {
        $validated = $this->validate([
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'slot_interval_minutes' => ['required', 'integer', 'in:5,10,15,20,30,60'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'reminder_hours_before' => ['required', 'integer', 'min:1', 'max:168'],
            'notify_clients' => ['boolean'],
            'gym_name' => ['required', 'string', 'max:255'],
            'gym_address' => ['nullable', 'string', 'max:255'],
            'gym_phone' => ['nullable', 'string', 'max:30'],
            'gym_email' => ['nullable', 'email', 'max:255'],
        ], attributes: [
            'buffer_minutes' => 'διάλειμμα',
            'slot_interval_minutes' => 'βήμα ωρών',
            'min_notice_hours' => 'ελάχιστος χρόνος πριν την κράτηση',
            'max_advance_days' => 'μέγιστο διάστημα στο μέλλον',
            'free_cancellation_hours' => 'δωρεάν ακύρωση',
            'reminder_hours_before' => 'υπενθύμιση',
            'gym_name' => 'όνομα γυμναστηρίου',
            'gym_address' => 'διεύθυνση',
            'gym_phone' => 'τηλέφωνο',
            'gym_email' => 'email',
        ]);

        $integerKeys = ['buffer_minutes', 'slot_interval_minutes', 'min_notice_hours', 'max_advance_days', 'free_cancellation_hours', 'reminder_hours_before'];

        foreach ($validated as $key => $value) {
            $validated[$key] = match (true) {
                in_array($key, $integerKeys, true) => (int) $value,
                is_bool($value) => $value,
                default => $value === '' ? null : $value,
            };
        }

        $settings->set($validated);

        $this->dispatch('notify', message: 'Οι ρυθμίσεις αποθηκεύτηκαν.');
    }

    public function render(): View
    {
        return view('livewire.settings.index');
    }
}
