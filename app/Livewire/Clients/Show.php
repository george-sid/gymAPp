<?php

namespace App\Livewire\Clients;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
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

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function upcomingBookings(): Collection
    {
        return $this->client->bookings()
            ->whereIn('status', BookingStatus::placeOccupyingValues())
            ->whereHas('trainingSession', fn (Builder $query) => $query->notCancelled()->where('starts_at', '>=', now()))
            ->with('trainingSession.service')
            ->get()
            ->sortBy('trainingSession.starts_at')
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function pastBookings(): Collection
    {
        return $this->client->bookings()
            ->whereHas('trainingSession', fn (Builder $query) => $query->where('starts_at', '<', now()))
            ->with('trainingSession.service')
            ->get()
            ->sortByDesc('trainingSession.starts_at')
            ->take(50)
            ->values();
    }

    /**
     * Counts of past bookings per status.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function attendance(): array
    {
        return $this->client->bookings()
            ->whereHas('trainingSession', fn (Builder $query) => $query->where('starts_at', '<', now()))
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function render(): View
    {
        return view('livewire.clients.show')->title($this->client->name);
    }
}
