<?php

namespace App\Livewire\Clients;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Πελάτες')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function clients(): LengthAwarePaginator
    {
        return User::query()
            ->clients()
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->status !== 'all', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->with('clientProfile')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.clients.index');
    }
}
