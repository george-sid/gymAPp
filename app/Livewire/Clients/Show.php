<?php

namespace App\Livewire\Clients;

use App\Actions\Workouts\AssignWorkoutPlan;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public User $client;

    #[Url(except: 'details')]
    public string $tab = 'details';

    public ?int $templateId = null;

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
            ->with(['trainingSession.service', 'clientPackage'])
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

    /**
     * @return Collection<int, WorkoutPlan>
     */
    #[Computed]
    public function workoutPlans(): Collection
    {
        return WorkoutPlan::query()
            ->where('client_id', $this->client->id)
            ->withCount('days')
            ->orderByDesc('is_active')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, WorkoutPlan>
     */
    #[Computed]
    public function planTemplates(): Collection
    {
        return WorkoutPlan::query()->templates()->where('trainer_id', auth()->id())->orderBy('name')->get(['id', 'name']);
    }

    public function assignTemplate(AssignWorkoutPlan $assignWorkoutPlan): void
    {
        $this->authorize('update', $this->client);

        $this->validate(['templateId' => ['required', Rule::in($this->planTemplates->modelKeys())]], attributes: ['templateId' => 'πρότυπο']);

        $plan = $assignWorkoutPlan->handle(WorkoutPlan::query()->findOrFail($this->templateId), $this->client, auth()->user());

        $this->redirectRoute('workout-plans.edit', $plan, navigate: true);
    }

    public function createEmptyPlan(): void
    {
        $this->authorize('update', $this->client);

        $plan = DB::transaction(function (): WorkoutPlan {
            WorkoutPlan::query()->where('client_id', $this->client->id)->update(['is_active' => false]);

            $plan = WorkoutPlan::query()->create([
                'trainer_id' => auth()->id(),
                'client_id' => $this->client->id,
                'name' => 'Πρόγραμμα '.$this->client->name,
                'assigned_at' => now(),
            ]);
            $plan->days()->create(['name' => 'Ημέρα 1', 'position' => 1]);

            return $plan;
        });

        $this->redirectRoute('workout-plans.edit', $plan, navigate: true);
    }

    public function activatePlan(int $planId): void
    {
        $this->authorize('update', $this->client);

        DB::transaction(function () use ($planId): void {
            $plan = WorkoutPlan::query()->where('client_id', $this->client->id)->findOrFail($planId);

            WorkoutPlan::query()->where('client_id', $this->client->id)->whereKeyNot($plan->id)->update(['is_active' => false]);
            $plan->update(['is_active' => true]);
        });

        unset($this->workoutPlans);
    }

    public function render(): View
    {
        return view('livewire.clients.show')->title($this->client->name);
    }
}
