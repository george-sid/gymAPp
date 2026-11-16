<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\CreateBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_client_and_records_who_did_it(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client($admin)->create();
        $session = TrainingSession::factory()->for($admin, 'trainer')->create();

        $booking = app(CreateBooking::class)->handle($session, $client, $admin);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertDatabaseHas('booking_status_changes', [
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'confirmed',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_rejects_booking_when_personal_session_is_taken(): void
    {
        $session = TrainingSession::factory()->create(['capacity' => 1]);
        Booking::factory()->for($session)->create();

        $this->expectExceptionObject(BookingException::sessionFull());

        app(CreateBooking::class)->handle($session, User::factory()->client()->create(), User::factory()->admin()->create());
    }

    public function test_group_session_accepts_clients_up_to_capacity(): void
    {
        $session = TrainingSession::factory()->group(2)->create();
        $admin = User::factory()->admin()->create();
        $action = app(CreateBooking::class);

        $action->handle($session, User::factory()->client()->create(), $admin);
        $action->handle($session, User::factory()->client()->create(), $admin);

        $this->expectExceptionObject(BookingException::sessionFull());

        $action->handle($session, User::factory()->client()->create(), $admin);
    }

    public function test_cancelled_bookings_do_not_take_a_place(): void
    {
        $session = TrainingSession::factory()->create(['capacity' => 1]);
        Booking::factory()->for($session)->status(BookingStatus::Cancelled)->create();

        $booking = app(CreateBooking::class)->handle($session, User::factory()->client()->create(), User::factory()->admin()->create());

        $this->assertSame(2, $session->bookings()->count());
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_rebooking_a_cancelled_client_reactivates_the_same_booking(): void
    {
        $session = TrainingSession::factory()->create();
        $cancelled = Booking::factory()->for($session)->status(BookingStatus::Cancelled)->create();

        $booking = app(CreateBooking::class)->handle($session, $cancelled->client, User::factory()->admin()->create());

        $this->assertTrue($booking->is($cancelled));
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->cancelled_at);
        $this->assertDatabaseHas('booking_status_changes', ['booking_id' => $booking->id, 'from_status' => 'cancelled', 'to_status' => 'confirmed']);
    }

    public function test_rejects_duplicate_booking_for_the_same_client(): void
    {
        $session = TrainingSession::factory()->group()->create();
        $booking = Booking::factory()->for($session)->create();

        $this->expectExceptionObject(BookingException::alreadyBooked());

        app(CreateBooking::class)->handle($session, $booking->client, User::factory()->admin()->create());
    }

    public function test_rejects_booking_into_cancelled_session(): void
    {
        $session = TrainingSession::factory()->cancelled()->create();

        $this->expectExceptionObject(BookingException::sessionCancelled());

        app(CreateBooking::class)->handle($session, User::factory()->client()->create(), User::factory()->admin()->create());
    }

    public function test_rejects_client_who_already_has_an_overlapping_booking(): void
    {
        $client = User::factory()->client()->create(['name' => 'Άννα']);
        $first = TrainingSession::factory()->at('2026-12-07 10:00')->create();
        Booking::factory()->for($first)->for($client, 'client')->create();
        $overlapping = TrainingSession::factory()->at('2026-12-07 10:30')->create();

        $this->expectExceptionObject(BookingException::clientBusy('Άννα'));

        app(CreateBooking::class)->handle($overlapping, $client, User::factory()->admin()->create());
    }

    public function test_rejects_staff_accounts(): void
    {
        $this->expectExceptionObject(BookingException::notAClient());

        app(CreateBooking::class)->handle(TrainingSession::factory()->create(), User::factory()->admin()->create(), User::factory()->admin()->create());
    }
}
