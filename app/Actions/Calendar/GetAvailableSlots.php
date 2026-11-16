<?php

namespace App\Actions\Calendar;

use App\Enums\AvailabilityExceptionType;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarEvent;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Free start times on a local day: weekly hours, adjusted by exceptions,
 * minus existing sessions (plus buffer) and blocked calendar time.
 */
class GetAvailableSlots
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  string  $localDate  Y-m-d in the display timezone
     * @param  bool  $applyBookingWindow  enforce minimum notice and maximum days ahead (client-facing bookings)
     * @return Collection<int, CarbonImmutable> UTC start times, ascending
     */
    public function handle(
        User $trainer,
        string $localDate,
        int $durationMinutes,
        ?int $ignoreSessionId = null,
        bool $applyBookingWindow = false,
    ): Collection {
        $day = CarbonImmutable::parse($localDate, LocalTime::zone())->startOfDay();
        $windows = $this->workingWindows($trainer, $day);

        if ($windows === []) {
            return collect();
        }

        $busy = $this->busyIntervals($trainer, $day, $ignoreSessionId);
        $step = max(5, $this->settings->integer('slot_interval_minutes'));
        $earliest = $applyBookingWindow ? CarbonImmutable::now()->addHours($this->settings->integer('min_notice_hours')) : null;
        $latest = $applyBookingWindow ? CarbonImmutable::now()->addDays($this->settings->integer('max_advance_days')) : null;

        $slots = collect();

        foreach ($windows as [$windowStart, $windowEnd]) {
            for ($start = $windowStart; $start->addMinutes($durationMinutes) <= $windowEnd; $start = $start->addMinutes($step)) {
                $startUtc = $start->utc();
                $endUtc = $startUtc->addMinutes($durationMinutes);

                if (($earliest && $startUtc < $earliest) || ($latest && $startUtc > $latest)) {
                    continue;
                }

                $isFree = collect($busy)->doesntContain(fn (array $interval) => $interval[0] < $endUtc && $interval[1] > $startUtc);

                if ($isFree) {
                    $slots->push($startUtc);
                }
            }
        }

        return $slots->unique(fn (CarbonImmutable $slot) => $slot->getTimestamp())->sort()->values();
    }

    /**
     * Local working intervals for the day after applying exceptions.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function workingWindows(User $trainer, CarbonImmutable $day): array
    {
        $windows = AvailabilityRule::query()
            ->where('trainer_id', $trainer->id)
            ->where('weekday', $day->isoWeekday())
            ->orderBy('start_time')
            ->get()
            ->map(fn (AvailabilityRule $rule) => [
                $day->setTimeFromTimeString($rule->start_time),
                $day->setTimeFromTimeString($rule->end_time),
            ])
            ->all();

        $exceptions = AvailabilityException::query()
            ->where('trainer_id', $trainer->id)
            ->coveringDate($day->toDateString())
            ->get();

        foreach ($exceptions->where('type', AvailabilityExceptionType::Extra) as $extra) {
            $windows[] = [$day->setTimeFromTimeString($extra->start_time), $day->setTimeFromTimeString($extra->end_time)];
        }

        foreach ($exceptions->where('type', AvailabilityExceptionType::Closed) as $closed) {
            if ($closed->isAllDay()) {
                return [];
            }

            $windows = $this->subtract($windows, $day->setTimeFromTimeString($closed->start_time), $day->setTimeFromTimeString($closed->end_time));
        }

        return $this->merge($windows);
    }

    /**
     * UTC intervals already taken: sessions (with buffer either side) and calendar events.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function busyIntervals(User $trainer, CarbonImmutable $day, ?int $ignoreSessionId): array
    {
        $buffer = $this->settings->integer('buffer_minutes');
        $from = $day->utc()->subMinutes($buffer);
        $to = $day->addDay()->utc()->addMinutes($buffer);

        $sessions = TrainingSession::query()
            ->where('trainer_id', $trainer->id)
            ->notCancelled()
            ->overlapping($from, $to)
            ->when($ignoreSessionId, fn ($query) => $query->whereKeyNot($ignoreSessionId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (TrainingSession $session) => [$session->starts_at->subMinutes($buffer), $session->ends_at->addMinutes($buffer)]);

        $events = CalendarEvent::query()
            ->where('trainer_id', $trainer->id)
            ->overlapping($from, $to)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (CalendarEvent $event) => [$event->starts_at, $event->ends_at]);

        return $sessions->concat($events)->all();
    }

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtract(array $windows, CarbonImmutable $closedStart, CarbonImmutable $closedEnd): array
    {
        $result = [];

        foreach ($windows as [$start, $end]) {
            if ($closedEnd <= $start || $closedStart >= $end) {
                $result[] = [$start, $end];

                continue;
            }

            if ($closedStart > $start) {
                $result[] = [$start, $closedStart];
            }

            if ($closedEnd < $end) {
                $result[] = [$closedEnd, $end];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function merge(array $windows): array
    {
        usort($windows, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($windows as $window) {
            $lastIndex = array_key_last($merged);

            if ($lastIndex !== null && $window[0] <= $merged[$lastIndex][1]) {
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $window[1]);

                continue;
            }

            $merged[] = $window;
        }

        return $merged;
    }
}
