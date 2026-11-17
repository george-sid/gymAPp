<?php

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The signed-in trainer's own account: details and password.
 */
#[Title('Προφίλ')]
class Edit extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
    }

    public function updateDetails(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update([...$validated, 'phone' => $validated['phone'] ?: null]);

        $this->dispatch('notify', message: 'Τα στοιχεία αποθηκεύτηκαν.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ], attributes: [
            'current_password' => 'τρέχων κωδικός',
            'password' => 'νέος κωδικός',
        ]);

        $user = auth()->user();
        $user->update(['password' => $this->password]);

        // Sign out every other device that was logged in with the old password.
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        session()->regenerate();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('notify', message: 'Ο κωδικός άλλαξε. Οι άλλες συσκευές αποσυνδέθηκαν.');
    }

    public function render(): View
    {
        return view('livewire.profile.edit');
    }
}
