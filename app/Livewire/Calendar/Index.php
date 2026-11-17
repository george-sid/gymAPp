<?php

namespace App\Livewire\Calendar;

use App\Actions\Calendar\CancelTrainingSession;
use App\Actions\Calendar\ChangeBookingStatus;
use App\Actions\Calendar\CreateBooking;
use App\Actions\Calendar\GetAvailableSlots;
use App\Actions\Calendar\MoveTrainingSession;
use App\Actions\Calendar\ScheduleSessions;
use App\Actions\Packages\GetClientAccount;
use App\Actions\Packages\WaiveBookingCharge;
use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingStatus;
use App\Enums\CalendarEventType;
use App\Exceptions\BookingException;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ημερολόγιο')]
class Index extends Component
{
    /** Create / edit modal: 'session' or 'event'. */
    public bool $showCreate = false;

    public string $createMode = 'session';

    public ?int $serviceId = null;

    public ?int $clientId = null;

    public string $date = '';

    public string $time = '';

    public int|string|null $weeks = 1;

    public string $notes = '';

    /** @var array<int, string> */
    public array $skipped = [];

    public ?int $editingEventId = null;

    public string $eventTitle = '';

    public string $eventType = 'personal';

    public string $eventStart = '';

    public string $eventEnd = '';

    /** Session details modal. */
    public ?int $sessionId = null;

    public ?int $addClientId = null;

    /**
     * Calendar entries between two local wall-clock times, in FullCalendar's format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function events(string $start, string $end): array
    {
        $from = LocalTime::toUtc($start);
        $to = LocalTime::toUtc($end);

        $sessions = TrainingSession::query()
            ->where('trainer_id', auth()->id())
            ->overlapping($from, $to)
            ->with(['service', 'activeBookings.client'])
            ->get()
            ->map(fn (TrainingSession $session) => $this->sessionEntry($session));

        $events = CalendarEvent::query()
            ->where('trainer_id', auth()->id())
            ->overlapping($from, $to)
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => 'e'.$event->id,
                'title' => $event->title,
                'start' => $event->localStartsAt()->format('Y-m-d\TH:i:s'),
                'end' => $event->localEndsAt()->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $event->type->color(),
                'borderColor' => $event->type->color(),
                'classNames' => ['fc-event-personal'],
                'extendedProps' => ['kind' => 'event', 'recordId' => $event->id],
            ]);

        return $sessions->concat($events)->concat($this->closures($start, $end))->values()->all();
    }

    public function openCreate(string $start, ?string $end = null): void
    {
        $startsAt = CarbonImmutable::parse($start);

        $this->resetCreateForm();
        $this->date = $startsAt->format('Y-m-d');
        $this->time = $startsAt->format('H:i');
        $this->eventStart = $startsAt->format('H:i');
        $this->eventEnd = ($end ? CarbonImmutable::parse($end) : $startsAt->addHour())->format('H:i');
        $this->serviceId = $this->services->first()?->id;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->resetCreateForm();
    }

    public function pickSlot(string $time): void
    {
        $this->time = $time;
    }

    /**
     * Free start times (local H:i) for the chosen service and day.
     *
     * @return SupportCollection<int, string>
     */
    #[Computed]
    public function availableSlots(): SupportCollection
    {
        $service = $this->services->firstWhere('id', $this->serviceId);

        if (! $service || ! $this->date || ! strtotime($this->date)) {
            return collect();
        }

        return app(GetAvailableSlots::class)
            ->handle(auth()->user(), $this->date, $service->duration_min)
            ->map(fn (CarbonImmutable $slot) => LocalTime::fromUtc($slot)->format('H:i'));
    }

    public function saveSession(ScheduleSessions $scheduleSessions): void
    {
        $this->validate([
            'serviceId' => ['required', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'clientId' => ['nullable', Rule::in($this->clients->modelKeys())],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'serviceId' => 'πρόγραμμα',
            'clientId' => 'πελάτης',
            'date' => 'ημερομηνία',
            'time' => 'ώρα',
            'weeks' => 'εβδομάδες',
        ]);

        try {
            $result = $scheduleSessions->handle(
                trainer: auth()->user(),
                service: Service::query()->findOrFail($this->serviceId),
                localStart: "{$this->date} {$this->time}",
                weeks: (int) $this->weeks,
                actor: auth()->user(),
                client: $this->clientId ? User::query()->findOrFail($this->clientId) : null,
                notes: $this->notes ?: null,
            );
        } catch (BookingException $exception) {
            throw ValidationException::withMessages(['time' => $exception->getMessage()]);
        }

        $this->dispatch('calendar-refresh');

        $created = count($result['created']);
        $this->dispatch('notify', message: $created === 1 ? 'Το ραντεβού κλείστηκε.' : "Κλείστηκαν {$created} ραντεβού.");

        if ($this->clientId) {
            $this->warnAboutPackageBalance(User::query()->findOrFail($this->clientId));
        }

        if ($result['skipped'] !== []) {
            $this->skipped = $result['skipped'];

            return;
        }

        $this->closeCreate();
    }

    public function openEvent(int $eventId): void
    {
        $event = $this->findEvent($eventId);

        $this->resetCreateForm();
        $this->createMode = 'event';
        $this->editingEventId = $event->id;
        $this->eventTitle = $event->title;
        $this->eventType = $event->type->value;
        $this->date = $event->localStartsAt()->format('Y-m-d');
        $this->eventStart = $event->localStartsAt()->format('H:i');
        $this->eventEnd = $event->localEndsAt()->format('H:i');
        $this->notes = (string) $event->notes;
        $this->showCreate = true;
    }

    public function saveEvent(): void
    {
        $validated = $this->validate([
            'eventTitle' => ['required', 'string', 'max:255'],
            'eventType' => ['required', Rule::enum(CalendarEventType::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'eventStart' => ['required', 'date_format:H:i'],
            'eventEnd' => ['required', 'date_format:H:i', 'after:eventStart'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'eventTitle' => 'τίτλος',
            'eventType' => 'τύπος',
            'date' => 'ημερομηνία',
            'eventStart' => 'ώρα έναρξης',
            'eventEnd' => 'ώρα λήξης',
        ]);

        $attributes = [
            'title' => $validated['eventTitle'],
            'type' => $validated['eventType'],
            'starts_at' => LocalTime::toUtc("{$this->date} {$this->eventStart}"),
            'ends_at' => LocalTime::toUtc("{$this->date} {$this->eventEnd}"),
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingEventId) {
            $this->findEvent($this->editingEventId)->update($attributes);
        } else {
            CalendarEvent::query()->create(['trainer_id' => auth()->id(), ...$attributes]);
        }

        $this->closeCreate();
        $this->dispatch('calendar-refresh');
        $this->dispatch('notify', message: 'Αποθηκεύτηκε στο ημερολόγιο.');
    }

    public function deleteEvent(): void
    {
        $this->findEvent((int) $this->editingEventId)->delete();

        $this->closeCreate();
        $this->dispatch('calendar-refresh');
    }

    public function openSession(int $sessionId): void
    {
        $this->sessionId = $this->findSession($sessionId)->id;
        $this->addClientId = null;
        $this->resetValidation();
    }

    public function closeSession(): void
    {
        $this->sessionId = null;
        unset($this->selectedSession);
    }

    #[Computed]
    public function selectedSession(): ?TrainingSession
    {
        if (! $this->sessionId) {
            return null;
        }

        return $this->findSession($this->sessionId)->load(['service', 'bookings' => fn ($query) => $query->with(['client', 'clientPackage'])->orderBy('booked_at')]);
    }

    public function addClientToSession(CreateBooking $createBooking): void
    {
        $this->validate(['addClientId' => ['required', Rule::in($this->clients->modelKeys())]], attributes: ['addClientId' => 'πελάτης']);

        $client = User::query()->findOrFail($this->addClientId);

        try {
            $createBooking->handle($this->findSession((int) $this->sessionId), $client, auth()->user());
        } catch (BookingException $exception) {
            throw ValidationException::withMessages(['addClientId' => $exception->getMessage()]);
        }

        $this->addClientId = null;
        unset($this->selectedSession);
        $this->dispatch('calendar-refresh');
        $this->warnAboutPackageBalance($client);
    }

    /**
     * Marks everyone still booked in the open session as attended (uses a package session each).
     */
    public function completeAll(ChangeBookingStatus $changeBookingStatus): void
    {
        $session = $this->findSession((int) $this->sessionId);

        $session->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->get()
            ->each(fn (Booking $booking) => $changeBookingStatus->handle($booking, BookingStatus::Completed, auth()->user()));

        unset($this->selectedSession);
        $this->dispatch('calendar-refresh');
        $this->dispatch('notify', message: 'Σημειώθηκαν ως ολοκληρωμένα.');
    }

    /**
     * Forgives a charged session (e.g. late cancellation) or charges it again.
     */
    public function toggleWaive(int $bookingId, WaiveBookingCharge $waiveBookingCharge): void
    {
        $booking = Booking::query()
            ->where('training_session_id', $this->findSession((int) $this->sessionId)->id)
            ->findOrFail($bookingId);

        $waiveBookingCharge->handle($booking, ! $booking->charge_waived);

        unset($this->selectedSession);
    }

    public function setBookingStatus(int $bookingId, string $status, ChangeBookingStatus $changeBookingStatus): void
    {
        $booking = Booking::query()
            ->where('training_session_id', $this->findSession((int) $this->sessionId)->id)
            ->findOrFail($bookingId);

        try {
            $changeBookingStatus->handle($booking, BookingStatus::from($status), auth()->user());
        } catch (BookingException $exception) {
            $this->dispatch('notify', message: $exception->getMessage(), type: 'error');
        }

        unset($this->selectedSession);
        $this->dispatch('calendar-refresh');
    }

    public function cancelSession(CancelTrainingSession $cancelTrainingSession): void
    {
        $cancelTrainingSession->handle($this->findSession((int) $this->sessionId), auth()->user());

        unset($this->selectedSession);
        $this->dispatch('calendar-refresh');
        $this->dispatch('notify', message: 'Η συνεδρία ακυρώθηκε.');
    }

    /**
     * Drag & drop / resize from the calendar. Returns false so the browser can undo the move.
     */
    public function moveEntry(string $kind, int $id, string $start, string $end, MoveTrainingSession $moveTrainingSession): bool
    {
        $startsAt = LocalTime::toUtc($start);
        $endsAt = LocalTime::toUtc($end);

        if ($kind === 'event') {
            $this->findEvent($id)->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

            return true;
        }

        try {
            $moveTrainingSession->handle($this->findSession($id), $startsAt, $endsAt);
        } catch (BookingException $exception) {
            $this->dispatch('notify', message: $exception->getMessage(), type: 'error');

            return false;
        }

        $this->dispatch('notify', message: 'Το ραντεβού μετακινήθηκε.');

        return true;
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function clients(): Collection
    {
        return User::query()->clients()->active()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']);
    }

    public function render(): View
    {
        return view('livewire.calendar.index', [
            'calendarConfig' => $this->calendarConfig(),
            'eventTypes' => CalendarEventType::cases(),
            'bookingStatuses' => BookingStatus::cases(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarConfig(): array
    {
        $rules = AvailabilityRule::query()->where('trainer_id', auth()->id())->get();

        return [
            'utcOffsetMinutes' => LocalTime::now()->utcOffset(),
            'slotMinTime' => '06:00:00',
            'slotMaxTime' => '23:00:00',
            'businessHours' => $rules->map(fn (AvailabilityRule $rule) => [
                'daysOfWeek' => [$rule->weekday % 7],
                'startTime' => substr($rule->start_time, 0, 5),
                'endTime' => substr($rule->end_time, 0, 5),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionEntry(TrainingSession $session): array
    {
        $booked = $session->activeBookings;
        $title = $session->service->name;

        if ($session->capacity === 1 && $booked->isNotEmpty()) {
            $title = $booked->first()->client->name.' · '.$title;
        } elseif ($session->capacity > 1) {
            $title .= " ({$booked->count()}/{$session->capacity})";
        }

        return [
            'id' => 's'.$session->id,
            'title' => $title,
            'start' => $session->localStartsAt()->format('Y-m-d\TH:i:s'),
            'end' => $session->localEndsAt()->format('Y-m-d\TH:i:s'),
            'backgroundColor' => $session->service->color,
            'borderColor' => $session->service->color,
            'editable' => ! $session->isCancelled(),
            'classNames' => $session->isCancelled() ? ['fc-event-cancelled'] : [],
            'extendedProps' => ['kind' => 'session', 'recordId' => $session->id],
        ];
    }

    /**
     * Closed days/hours as background shading.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    private function closures(string $start, string $end): SupportCollection
    {
        $rangeStart = CarbonImmutable::parse($start)->toDateString();
        $rangeEnd = CarbonImmutable::parse($end)->toDateString();

        return AvailabilityException::query()
            ->where('trainer_id', auth()->id())
            ->where('type', AvailabilityExceptionType::Closed)
            ->whereDate('starts_on', '<=', $rangeEnd)
            ->whereDate('ends_on', '>=', $rangeStart)
            ->get()
            ->flatMap(function (AvailabilityException $exception) {
                if ($exception->isAllDay()) {
                    return [[
                        'start' => $exception->starts_on->format('Y-m-d'),
                        'end' => $exception->ends_on->addDay()->format('Y-m-d'),
                        'display' => 'background',
                        'backgroundColor' => '#fecaca',
                        'title' => $exception->reason ?? 'Κλειστό',
                    ]];
                }

                return collect(CarbonPeriodImmutable::create($exception->starts_on, $exception->ends_on))
                    ->map(fn (CarbonImmutable $day) => [
                        'start' => $day->format('Y-m-d').'T'.substr($exception->start_time, 0, 5).':00',
                        'end' => $day->format('Y-m-d').'T'.substr($exception->end_time, 0, 5).':00',
                        'display' => 'background',
                        'backgroundColor' => '#fecaca',
                    ]);
            });
    }

    private function warnAboutPackageBalance(User $client): void
    {
        if ($warning = app(GetClientAccount::class)->bookingWarning($client)) {
            $this->dispatch('notify', message: $warning, type: 'warning');
        }
    }

    private function findSession(int $sessionId): TrainingSession
    {
        return TrainingSession::query()->where('trainer_id', auth()->id())->findOrFail($sessionId);
    }

    private function findEvent(int $eventId): CalendarEvent
    {
        return CalendarEvent::query()->where('trainer_id', auth()->id())->findOrFail($eventId);
    }

    private function resetCreateForm(): void
    {
        $this->reset(['createMode', 'serviceId', 'clientId', 'date', 'time', 'weeks', 'notes', 'skipped', 'editingEventId', 'eventTitle', 'eventType', 'eventStart', 'eventEnd']);
        $this->resetValidation();
    }
}
