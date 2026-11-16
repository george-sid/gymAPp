<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\ScheduleSessions;
use App\Exceptions\BookingException;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_single_session_with_the_service_duration_and_capacity(): void
    {
        $trainer = User::factory()->admin()->create();
        $service = Service::factory()->group(6)->create(['duration_min' => 45]);

        $result = app(ScheduleSessions::class)->handle($trainer, $service, '2026-12-07 18:00', 1, $trainer);

        $session = $result['created'][0];
        $this->assertSame('2026-12-07 18:45', $session->localEndsAt()->format('Y-m-d H:i'));
        $this->assertSame(6, $session->capacity);
        $this->assertNull($session->recurrence_id);
    }

    public function test_weekly_series_keeps_the_local_hour_across_daylight_saving_time(): void
    {
        $trainer = User::factory()->admin()->create();
        $client = User::factory()->client($trainer)->create();

        // Tuesdays 18:00 from 16 March 2027; clocks change on 28 March.
        $result = app(ScheduleSessions::class)->handle($trainer, Service::factory()->create(), '2027-03-16 18:00', 4, $trainer, $client);

        $this->assertCount(4, $result['created']);
        $this->assertSame([], $result['skipped']);

        $sessions = TrainingSession::query()->orderBy('starts_at')->get();
        $this->assertSame(['18:00', '18:00', '18:00', '18:00'], $sessions->map(fn ($s) => $s->localStartsAt()->format('H:i'))->all());
        $this->assertSame(['16:00', '16:00', '15:00', '15:00'], $sessions->map(fn ($s) => $s->starts_at->format('H:i'))->all());
        $this->assertCount(1, $sessions->pluck('recurrence_id')->unique());
        $this->assertSame(4, $client->bookings()->count());
    }

    public function test_series_skips_weeks_that_conflict(): void
    {
        $trainer = User::factory()->admin()->create();
        TrainingSession::factory()->for($trainer, 'trainer')->at('2026-12-15 18:00')->create();

        $result = app(ScheduleSessions::class)->handle($trainer, Service::factory()->create(), '2026-12-08 18:00', 3, $trainer);

        $this->assertCount(2, $result['created']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringStartsWith('15/12/2026 18:00', $result['skipped'][0]);
    }

    public function test_single_session_conflict_is_reported(): void
    {
        $trainer = User::factory()->admin()->create();
        TrainingSession::factory()->for($trainer, 'trainer')->at('2026-12-08 18:00')->create();

        $this->expectException(BookingException::class);

        app(ScheduleSessions::class)->handle($trainer, Service::factory()->create(), '2026-12-08 18:30', 1, $trainer);
    }

    public function test_failed_booking_does_not_leave_an_empty_session_behind(): void
    {
        $trainer = User::factory()->admin()->create();
        $client = User::factory()->client($trainer)->create();
        $busy = TrainingSession::factory()->at('2026-12-08 18:00')->create();
        $busy->bookings()->create(['client_id' => $client->id, 'status' => 'confirmed', 'booked_at' => now()]);

        try {
            app(ScheduleSessions::class)->handle($trainer, Service::factory()->create(), '2026-12-08 18:00', 1, $trainer, $client);
            $this->fail('Expected a booking conflict.');
        } catch (BookingException) {
            $this->assertSame(0, TrainingSession::query()->where('trainer_id', $trainer->id)->count());
        }
    }
}
