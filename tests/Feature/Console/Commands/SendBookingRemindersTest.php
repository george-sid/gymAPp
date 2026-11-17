<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\SessionReminderNotification;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBookingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(LocalTime::toUtc('2026-12-07 10:00'));
    }

    public function test_reminds_clients_with_a_session_in_the_next_24_hours_once(): void
    {
        $booking = $this->bookingAt('2026-12-08 09:00');

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($booking->client, SessionReminderNotification::class, 1);
        $this->assertNotNull($booking->refresh()->reminder_sent_at);
    }

    public function test_skips_sessions_outside_the_window_and_inactive_bookings(): void
    {
        $tooLate = $this->bookingAt('2026-12-08 11:00');
        $past = $this->bookingAt('2026-12-07 09:00');
        $cancelled = $this->bookingAt('2026-12-07 18:00', BookingStatus::Cancelled);
        $cancelledSession = $this->bookingAt('2026-12-07 19:00');
        $cancelledSession->trainingSession->update(['status' => 'cancelled']);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($tooLate->refresh()->reminder_sent_at);
        $this->assertNull($past->refresh()->reminder_sent_at);
        $this->assertNull($cancelled->refresh()->reminder_sent_at);
    }

    public function test_skips_clients_without_email_or_inactive(): void
    {
        $this->bookingAt('2026-12-07 18:00', client: User::factory()->client()->create(['email' => null]));
        $this->bookingAt('2026-12-07 19:00', client: User::factory()->client()->inactive()->create());

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_email_names_the_session_in_greek_local_time(): void
    {
        $booking = $this->bookingAt('2026-12-08 09:00');
        $booking->load(['client', 'trainingSession.service']);

        $html = (string) (new SessionReminderNotification($booking))->toMail($booking->client)->render();

        $this->assertStringContainsString('Personal 1:1', $html);
        $this->assertStringContainsString('Τρίτη 8 Δεκεμβρίου, 09:00', $html);
    }

    private function bookingAt(string $localStart, BookingStatus $status = BookingStatus::Confirmed, ?User $client = null): Booking
    {
        $session = TrainingSession::factory()
            ->for(Service::query()->firstOrCreate(['name' => 'Personal 1:1'], ['duration_min' => 60]))
            ->at($localStart)
            ->create();

        return Booking::factory()
            ->for($session)
            ->for($client ?? User::factory()->client()->create(), 'client')
            ->status($status)
            ->create();
    }
}
