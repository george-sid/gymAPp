<?php

namespace App\Actions\Calendar;

use App\Enums\AvailabilityExceptionType;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;

/**
 * A trainer's working intervals on a local day: weekly hours plus extra hours,
 * minus closures. Intervals are local times, sorted and non-overlapping.
 */
class GetWorkingWindows
{
    /**
     * @param  string  $localDate  Y-m-d in the display timezone
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function handle(User $trainer, string $localDate): array
    {
        $day = CarbonImmutable::parse($localDate, LocalTime::zone())->startOfDay();

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
     * Total working minutes on a local day.
     */
    public function minutes(User $trainer, string $localDate): int
    {
        return (int) array_sum(array_map(
            fn (array $window) => $window[0]->diffInMinutes($window[1]),
            $this->handle($trainer, $localDate),
        ));
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
