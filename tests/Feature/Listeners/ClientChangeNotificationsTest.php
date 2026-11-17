<?php

namespace Tests\Feature\Listeners;

use App\Actions\Calendar\CancelTrainingSession;
use App\Actions\Calendar\ChangeBookingStatus;
use App\Actions\Calendar\MoveTrainingSession;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\SessionRescheduledNotification;
use App\Support\LocalTime;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientChangeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(LocalTime::toUtc('2026-12-01 10:00'));
        $this->trainer = User::factory()->admin()->create();
    }

    public function test_moving_a_session_emails_booked_clients_and_resets_their_reminder(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->group()->at('2026-12-07 10:00')->create();
        $booking = Booking::factory()->for($session)->create(['reminder_sent_at' => now()]);
        $cancelled = Booking::factory()->for($session)->status(BookingStatus::Cancelled)->create();

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 12:00'));

        Notification::assertSentTo($booking->client, SessionRescheduledNotification::class, function (SessionRescheduledNotification $notification) {
            return $notification->previousStartsAt->equalTo(LocalTime::toUtc('2026-12-07 10:00'));
        });
        Notification::assertNotSentTo($cancelled->client, SessionRescheduledNotification::class);
        $this->assertNull($booking->refresh()->reminder_sent_at);
    }

    public function test_changing_only_the_end_time_sends_nothing(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-07 10:00')->create();
        Booking::factory()->for($session)->create();

        app(MoveTrainingSession::class)->handle($session, LocalTime::toUtc('2026-12-07 10:00'), LocalTime::toUtc('2026-12-07 11:30'));

        Notification::assertNothingSent();
    }

    public function test_cancelling_a_booking_emails_the_client(): void
    {
        $booking = Booking::factory()->for(TrainingSession::factory()->at('2026-12-07 10:00'))->create();

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, $this->trainer);

        Notification::assertSentTo($booking->client, BookingCancelledNotification::class);
    }

    public function test_cancelling_a_session_emails_every_booked_client(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->group()->at('2026-12-07 10:00')->create();
        $bookings = Booking::factory()->count(2)->for($session)->create();

        app(CancelTrainingSession::class)->handle($session, $this->trainer);

        foreach ($bookings as $booking) {
            Notification::assertSentTo($booking->client, BookingCancelledNotification::class);
        }
    }

    public function test_no_emails_for_past_sessions_or_when_disabled(): void
    {
        $past = Booking::factory()->for(TrainingSession::factory()->at('2026-11-20 10:00'))->create();
        app(ChangeBookingStatus::class)->handle($past, BookingStatus::Cancelled, $this->trainer);

        app(Settings::class)->set(['notify_clients' => false]);
        $future = Booking::factory()->for(TrainingSession::factory()->at('2026-12-07 10:00'))->create();
        app(ChangeBookingStatus::class)->handle($future, BookingStatus::Cancelled, $this->trainer);

        Notification::assertNothingSent();
    }
}
