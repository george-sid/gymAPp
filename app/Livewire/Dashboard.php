<?php

namespace App\Livewire;

use App\Actions\Calendar\GetWorkingWindows;
use App\Enums\BookingStatus;
use App\Models\BookingStatusChange;
use App\Models\ClientPackage;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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

    /**
     * Payments received this calendar month (local time).
     */
    #[Computed]
    public function revenueThisMonth(): float
    {
        $today = LocalTime::now();

        return (float) Payment::query()
            ->whereHas('client', fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->whereBetween('paid_on', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])
            ->sum('amount');
    }

    /**
     * Packages with at most one session left or expiring within 7 days.
     *
     * @return Collection<int, ClientPackage>
     */
    #[Computed]
    public function packagesRunningOut(): Collection
    {
        $today = LocalTime::now()->toDateString();
        $inAWeek = LocalTime::now()->addDays(7)->toDateString();

        return ClientPackage::query()
            ->whereHas('client', fn (Builder $query) => $query->active()->visibleTo(auth()->user()))
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', $today))
            ->withUsage()
            ->with('client')
            ->orderBy('expires_on')
            ->get()
            ->filter(fn (ClientPackage $package) => $package->remainingSessions() <= 1
                || ($package->expires_on && $package->expires_on->toDateString() <= $inAWeek))
            ->take(8)
            ->values();
    }

    /**
     * Clients who owe money or sessions, largest amount first.
     *
     * @return SupportCollection<int, array{client: User, amount_due: float, owed_sessions: int}>
     */
    #[Computed]
    public function clientsOwing(): SupportCollection
    {
        return User::query()
            ->clients()
            ->visibleTo(auth()->user())
            ->withSum('clientPackages', 'price')
            ->withSum('payments', 'amount')
            ->withCount(['bookings as owed_sessions' => fn (Builder $query) => $query->whereNotNull('charged_at')->whereNull('client_package_id')])
            ->get()
            ->map(fn (User $client) => [
                'client' => $client,
                'amount_due' => round((float) $client->client_packages_sum_price - (float) $client->payments_sum_amount, 2),
                'owed_sessions' => (int) $client->owed_sessions,
            ])
            ->filter(fn (array $row) => $row['amount_due'] > 0 || $row['owed_sessions'] > 0)
            ->sortByDesc('amount_due')
            ->take(8)
            ->values();
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
