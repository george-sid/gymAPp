<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\MoveTrainingSession;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveTrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_moves_session_keeping_its_duration(): void
    {
        $session = TrainingSession::factory()->at('2026-12-07 10:00', 45)->create();

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-08 18:00'));

        $session->refresh();
        $this->assertSame('2026-12-08 18:00', $session->localStartsAt()->format('Y-m-d H:i'));
        $this->assertSame('2026-12-08 18:45', $session->localEndsAt()->format('Y-m-d H:i'));
    }

    public function test_can_change_the_end_time(): void
    {
        $session = TrainingSession::factory()->at('2026-12-07 10:00')->create();

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 10:00'), LocalTime::toUtc('2026-12-07 11:30'));

        $this->assertSame(90, $session->refresh()->durationInMinutes());
    }

    public function test_rejects_move_onto_another_session_of_the_trainer(): void
    {
        $trainer = User::factory()->admin()->create();
        TrainingSession::factory()->for($trainer, 'trainer')->at('2026-12-07 18:00')->create();
        $session = TrainingSession::factory()->for($trainer, 'trainer')->at('2026-12-07 10:00')->create();

        $this->expectExceptionObject(BookingException::trainerBusy());

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 18:30'));
    }

    public function test_rejects_move_onto_blocked_time(): void
    {
        $trainer = User::factory()->admin()->create();
        CalendarEvent::factory()->for($trainer, 'trainer')->at('2026-12-07 13:00')->create();
        $session = TrainingSession::factory()->for($trainer, 'trainer')->at('2026-12-07 10:00')->create();

        $this->expectExceptionObject(BookingException::trainerBusy());

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 13:00'));
    }

    public function test_rejects_move_when_a_booked_client_is_busy_at_the_new_time(): void
    {
        $client = User::factory()->client()->create(['name' => 'Πέτρος']);
        $session = TrainingSession::factory()->at('2026-12-07 10:00')->create();
        Booking::factory()->for($session)->for($client, 'client')->create();
        $otherSession = TrainingSession::factory()->at('2026-12-07 18:00')->create();
        Booking::factory()->for($otherSession)->for($client, 'client')->create();

        $this->expectExceptionObject(BookingException::clientBusy('Πέτρος'));

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 18:00'));
    }

    public function test_rejects_moving_a_cancelled_session(): void
    {
        $this->expectExceptionObject(BookingException::sessionCancelled());

        app(MoveTrainingSession::class)->handle(TrainingSession::factory()->cancelled()->create(), LocalTime::toUtc('2026-12-08 10:00'));
    }
}
