<?php

namespace App\Livewire\Clients;

use App\Actions\Clients\SaveClient;
use App\Enums\Gender;
use App\Livewire\Forms\ClientForm;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Create a new client (no route parameter) or edit an existing one.
 */
class Edit extends Component
{
    public ClientForm $form;

    public function mount(?User $client = null): void
    {
        if ($client?->exists) {
            $this->authorize('update', $client);
            $this->form->setClient($client->load('clientProfile'));

            return;
        }

        $this->authorize('create', User::class);
    }

    public function save(SaveClient $saveClient): void
    {
        if ($this->form->client) {
            $this->authorize('update', $this->form->client);
        }

        $client = $this->form->save($saveClient, auth()->user());

        session()->flash('status', 'Ο πελάτης αποθηκεύτηκε.');

        $this->redirectRoute('clients.show', $client, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clients.edit', [
            'genders' => Gender::cases(),
        ])->title($this->form->client ? 'Επεξεργασία πελάτη' : 'Νέος πελάτης');
    }
}
