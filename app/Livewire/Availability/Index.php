<?php

namespace App\Livewire\Availability;

use App\Actions\Availability\SaveWeeklySchedule;
use App\Enums\AvailabilityExceptionType;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Διαθεσιμότητα')]
class Index extends Component
{
    /**
     * Working hours per ISO weekday, as HH:MM local times.
     *
     * @var array<int, array<int, array{start: string, end: string}>>
     */
    public array $schedule = [];

    public string $exceptionType = 'closed';

    public string $exceptionStartsOn = '';

    public string $exceptionEndsOn = '';

    public bool $exceptionAllDay = true;

    public string $exceptionStartTime = '';

    public string $exceptionEndTime = '';

    public string $exceptionReason = '';

    public function mount(): void
    {
        $this->schedule = array_fill_keys(array_keys(AvailabilityRule::WEEKDAYS), []);

        AvailabilityRule::query()
            ->where('trainer_id', auth()->id())
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->each(function (AvailabilityRule $rule): void {
                $this->schedule[$rule->weekday][] = [
                    'start' => substr($rule->start_time, 0, 5),
                    'end' => substr($rule->end_time, 0, 5),
                ];
            });
    }

    public function addRange(int $weekday): void
    {
        $last = end($this->schedule[$weekday]) ?: null;

        $this->schedule[$weekday][] = $last
            ? ['start' => $last['end'], 'end' => $last['end']]
            : ['start' => '09:00', 'end' => '14:00'];
    }

    public function removeRange(int $weekday, int $index): void
    {
        unset($this->schedule[$weekday][$index]);
        $this->schedule[$weekday] = array_values($this->schedule[$weekday]);
    }

    /**
     * Copies one day's hours to every weekday (Monday–Friday).
     */
    public function copyToWeekdays(int $weekday): void
    {
        foreach (range(1, 5) as $day) {
            $this->schedule[$day] = $this->schedule[$weekday];
        }
    }

    public function saveSchedule(SaveWeeklySchedule $saveWeeklySchedule): void
    {
        $this->validate([
            'schedule' => ['array'],
            'schedule.*' => ['array'],
            'schedule.*.*.start' => ['required', 'date_format:H:i'],
            'schedule.*.*.end' => ['required', 'date_format:H:i'],
        ], attributes: [
            'schedule.*.*.start' => 'ώρα έναρξης',
            'schedule.*.*.end' => 'ώρα λήξης',
        ]);

        $this->ensureRangesAreValid();

        $saveWeeklySchedule->handle(auth()->user(), $this->schedule);

        $this->dispatch('notify', message: 'Το ωράριο αποθηκεύτηκε.');
    }

    public function addException(): void
    {
        if ($this->exceptionType === AvailabilityExceptionType::Extra->value) {
            $this->exceptionAllDay = false;
        }

        $validated = $this->validate([
            'exceptionType' => ['required', Rule::enum(AvailabilityExceptionType::class)],
            'exceptionStartsOn' => ['required', 'date'],
            'exceptionEndsOn' => ['nullable', 'date', 'after_or_equal:exceptionStartsOn'],
            'exceptionStartTime' => [Rule::requiredIf(! $this->exceptionAllDay), 'nullable', 'date_format:H:i'],
            'exceptionEndTime' => [Rule::requiredIf(! $this->exceptionAllDay), 'nullable', 'date_format:H:i', 'after:exceptionStartTime'],
            'exceptionReason' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'exceptionType' => 'τύπος',
            'exceptionStartsOn' => 'από',
            'exceptionEndsOn' => 'έως',
            'exceptionStartTime' => 'ώρα έναρξης',
            'exceptionEndTime' => 'ώρα λήξης',
            'exceptionReason' => 'αιτία',
        ]);

        AvailabilityException::query()->create([
            'trainer_id' => auth()->id(),
            'type' => $validated['exceptionType'],
            'starts_on' => $validated['exceptionStartsOn'],
            'ends_on' => $validated['exceptionEndsOn'] ?: $validated['exceptionStartsOn'],
            'start_time' => $this->exceptionAllDay ? null : $validated['exceptionStartTime'],
            'end_time' => $this->exceptionAllDay ? null : $validated['exceptionEndTime'],
            'reason' => $validated['exceptionReason'] ?: null,
        ]);

        $this->reset(['exceptionType', 'exceptionStartsOn', 'exceptionEndsOn', 'exceptionAllDay', 'exceptionStartTime', 'exceptionEndTime', 'exceptionReason']);
        unset($this->exceptions);

        $this->dispatch('notify', message: 'Η εξαίρεση προστέθηκε.');
    }

    public function deleteException(int $exceptionId): void
    {
        AvailabilityException::query()
            ->where('trainer_id', auth()->id())
            ->findOrFail($exceptionId)
            ->delete();

        unset($this->exceptions);
    }

    /**
     * Current and upcoming exceptions.
     *
     * @return Collection<int, AvailabilityException>
     */
    #[Computed]
    public function exceptions(): Collection
    {
        return AvailabilityException::query()
            ->where('trainer_id', auth()->id())
            ->whereDate('ends_on', '>=', now(config('app.display_timezone'))->toDateString())
            ->orderBy('starts_on')
            ->orderBy('start_time')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.availability.index', [
            'weekdays' => AvailabilityRule::WEEKDAYS,
            'exceptionTypes' => AvailabilityExceptionType::cases(),
        ]);
    }

    /**
     * Each range must end after it starts and must not overlap another range of the same day.
     */
    private function ensureRangesAreValid(): void
    {
        $errors = [];

        foreach ($this->schedule as $weekday => $ranges) {
            foreach ($ranges as $index => $range) {
                if ($range['end'] <= $range['start']) {
                    $errors["schedule.{$weekday}.{$index}.end"] = 'Η ώρα λήξης πρέπει να είναι μετά την ώρα έναρξης.';
                }
            }

            $sorted = collect($ranges)->sortBy('start')->values();

            foreach ($sorted as $index => $range) {
                $next = $sorted->get($index + 1);

                if ($next && $next['start'] < $range['end']) {
                    $errors["schedule.{$weekday}"] = 'Οι ώρες επικαλύπτονται.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
