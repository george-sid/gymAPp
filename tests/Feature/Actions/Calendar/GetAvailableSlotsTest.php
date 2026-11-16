<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\GetAvailableSlots;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarEvent;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    private const string MONDAY = '2026-12-07';

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 08:00', 'UTC'));
        $this->trainer = User::factory()->admin()->create();
        AvailabilityRule::factory()->for($this->trainer, 'trainer')->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);
    }

    public function test_returns_slots_within_working_hours_at_the_configured_interval(): void
    {
        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00'], $this->localSlots(self::MONDAY, 60));
    }

    public function test_returns_nothing_on_a_day_without_working_hours(): void
    {
        $this->assertSame([], $this->localSlots('2026-12-08', 60));
    }

    public function test_excludes_existing_sessions_and_the_buffer_around_them(): void
    {
        $this->setting('buffer_minutes', 0);
        TrainingSession::factory()->for($this->trainer, 'trainer')->at(self::MONDAY.' 10:00')->create();

        $this->assertSame(['09:00', '11:00'], $this->localSlots(self::MONDAY, 60));

        $this->setting('buffer_minutes', 30);

        $this->assertSame([], $this->localSlots(self::MONDAY, 60));
    }

    public function test_ignores_cancelled_sessions_and_other_trainers(): void
    {
        $this->setting('buffer_minutes', 0);
        TrainingSession::factory()->for($this->trainer, 'trainer')->cancelled()->at(self::MONDAY.' 10:00')->create();
        TrainingSession::factory()->at(self::MONDAY.' 09:00', 180)->create();

        $this->assertCount(5, $this->localSlots(self::MONDAY, 60));
    }

    public function test_can_ignore_the_session_being_moved(): void
    {
        $this->setting('buffer_minutes', 0);
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->at(self::MONDAY.' 10:00')->create();

        $slots = app(GetAvailableSlots::class)->handle($this->trainer, self::MONDAY, 60, ignoreSessionId: $session->id);

        $this->assertCount(5, $slots);
    }

    public function test_excludes_blocked_calendar_time(): void
    {
        $this->setting('buffer_minutes', 0);
        CalendarEvent::factory()->for($this->trainer, 'trainer')->at(self::MONDAY.' 09:00', 90)->create();

        $this->assertSame(['10:30', '11:00'], $this->localSlots(self::MONDAY, 60));
    }

    public function test_all_day_closure_removes_every_slot(): void
    {
        AvailabilityException::factory()->for($this->trainer, 'trainer')->create(['starts_on' => '2026-12-05', 'ends_on' => '2026-12-10']);

        $this->assertSame([], $this->localSlots(self::MONDAY, 60));
    }

    public function test_partial_closure_splits_the_working_hours(): void
    {
        AvailabilityException::factory()->for($this->trainer, 'trainer')->create([
            'starts_on' => self::MONDAY,
            'ends_on' => self::MONDAY,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $this->assertSame(['09:00', '11:00'], $this->localSlots(self::MONDAY, 60));
    }

    public function test_extra_hours_open_a_day_without_working_hours(): void
    {
        AvailabilityException::factory()->for($this->trainer, 'trainer')->extra('10:00', '12:00')->create([
            'starts_on' => '2026-12-06',
            'ends_on' => '2026-12-06',
        ]);

        $this->assertSame(['10:00', '10:30', '11:00'], $this->localSlots('2026-12-06', 60));
    }

    public function test_local_hours_are_converted_to_utc_across_daylight_saving_time(): void
    {
        $winter = app(GetAvailableSlots::class)->handle($this->trainer, self::MONDAY, 60)->first();
        $summer = app(GetAvailableSlots::class)->handle($this->trainer, '2027-03-29', 60)->first();

        $this->assertSame('07:00', $winter->utc()->format('H:i'));
        $this->assertSame('06:00', $summer->utc()->format('H:i'));
    }

    public function test_booking_window_hides_slots_inside_minimum_notice_and_beyond_max_days(): void
    {
        $this->setting('min_notice_hours', 2);
        $this->travelTo(LocalTime::toUtc(self::MONDAY.' 08:30'));

        $slots = app(GetAvailableSlots::class)->handle($this->trainer, self::MONDAY, 60, applyBookingWindow: true)
            ->map(fn (CarbonImmutable $slot) => LocalTime::fromUtc($slot)->format('H:i'))
            ->all();

        $this->assertSame(['10:30', '11:00'], $slots);

        $this->setting('max_advance_days', 30);
        $this->travelTo(LocalTime::toUtc('2026-10-01 08:00'));

        $this->assertCount(0, app(GetAvailableSlots::class)->handle($this->trainer, self::MONDAY, 60, applyBookingWindow: true));
    }

    /**
     * @return array<int, string>
     */
    private function localSlots(string $date, int $minutes): array
    {
        return app(GetAvailableSlots::class)
            ->handle($this->trainer, $date, $minutes)
            ->map(fn (CarbonImmutable $slot) => LocalTime::fromUtc($slot)->format('H:i'))
            ->all();
    }

    private function setting(string $key, int $value): void
    {
        app(Settings::class)->set([$key => $value]);
    }
}
