<?php

namespace App\Actions\Calendar;

use App\Models\CalendarEvent;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Free start times on a local day: working hours minus existing sessions
 * (plus buffer) and blocked calendar time.
 */
class GetAvailableSlots
{
    public function __construct(
        private Settings $settings,
        private GetWorkingWindows $getWorkingWindows,
    ) {}

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
        $windows = $this->getWorkingWindows->handle($trainer, $localDate);

        if ($windows === []) {
            return collect();
        }

        $day = CarbonImmutable::parse($localDate, LocalTime::zone())->startOfDay();
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
}
