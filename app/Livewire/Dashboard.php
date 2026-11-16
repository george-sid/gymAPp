<?php

namespace App\Livewire;

use App\Actions\Calendar\GetWorkingWindows;
use App\Enums\BookingStatus;
use App\Models\BookingStatusChange;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Αρχική')]
class Dashboard extends Component
{
    /**
     * Today's sessions on the trainer's calendar.
     *
     * @return Collection<int, TrainingSession>
     */
    #[Computed]
    public function todaySessions(): Collection
    {
        [$from, $to] = LocalTime::dayBoundsUtc(LocalTime::now()->toDateString());

        return TrainingSession::query()
            ->where('trainer_id', auth()->id())
            ->notCancelled()
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->with(['service', 'activeBookings.client'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * This week's (Monday–Sunday, local) sessions and how much of the working time is booked.
     *
     * @return array{sessions: int, booked_minutes: int, working_minutes: int, occupancy: ?int}
     */
    #[Computed]
    public function week(): array
    {
        $weekStart = LocalTime::now()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addWeek();

        $sessions = TrainingSession::query()
            ->where('trainer_id', auth()->id())
            ->notCancelled()
            ->where('starts_at', '>=', $weekStart->utc())
            ->where('starts_at', '<', $weekEnd->utc())
            ->withCount('activeBookings')
            ->get();

        $bookedMinutes = $sessions
            ->filter(fn (TrainingSession $session) => $session->active_bookings_count > 0)
            ->sum(fn (TrainingSession $session) => $session->durationInMinutes());

        $getWorkingWindows = app(GetWorkingWindows::class);
        $workingMinutes = collect(CarbonPeriodImmutable::create($weekStart, $weekEnd->subDay()))
            ->sum(fn ($day) => $getWorkingWindows->minutes(auth()->user(), $day->toDateString()));

        return [
            'sessions' => $sessions->count(),
            'booked_minutes' => (int) $bookedMinutes,
            'working_minutes' => (int) $workingMinutes,
            'occupancy' => $workingMinutes > 0 ? (int) min(100, round($bookedMinutes / $workingMinutes * 100)) : null,
        ];
    }

    #[Computed]
    public function activeClientsCount(): int
    {
        return User::query()->clients()->active()->visibleTo(auth()->user())->count();
    }

    #[Computed]
    public function cancellationsLastWeek(): int
    {
        return $this->recentChangesQuery()->where('to_status', BookingStatus::Cancelled)->count();
    }

    /**
     * New bookings, cancellations and no-shows of the last 7 days.
     *
     * @return Collection<int, BookingStatusChange>
     */
    #[Computed]
    public function recentActivity(): Collection
    {
        return $this->recentChangesQuery()
            ->where(fn (Builder $query) => $query
                ->whereIn('to_status', [BookingStatus::Cancelled, BookingStatus::NoShow])
                ->orWhereNull('from_status'))
            ->with(['booking.client', 'booking.trainingSession.service'])
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }

    /**
     * @return Builder<BookingStatusChange>
     */
    private function recentChangesQuery(): Builder
    {
        return BookingStatusChange::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('booking.trainingSession', fn (Builder $query) => $query->where('trainer_id', auth()->id()));
    }
}
