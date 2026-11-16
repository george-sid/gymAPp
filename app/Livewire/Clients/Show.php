<?php

namespace App\Livewire\Clients;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public User $client;

    #[Url(except: 'details')]
    public string $tab = 'details';

    public function mount(User $client): void
    {
        $this->authorize('view', $client);

        $this->client = $client->load('clientProfile.trainer');
    }

    public function render(): View
    {
        return view('livewire.clients.show')->title($this->client->name);
    }
}
