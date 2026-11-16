<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_records_who_and_when(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create();

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, $admin);

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame($admin->id, $booking->cancelled_by);
        $this->assertDatabaseHas('booking_status_changes', ['booking_id' => $booking->id, 'from_status' => 'confirmed', 'to_status' => 'cancelled', 'changed_by' => $admin->id]);
    }

    public function test_marks_attendance(): void
    {
        $booking = Booking::factory()->create();
        $admin = User::factory()->admin()->create();

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::NoShow, $admin);

        $this->assertSame(BookingStatus::NoShow, $booking->refresh()->status);
        $this->assertSame(1, $booking->statusChanges()->count());
    }

    public function test_restoring_a_cancelled_booking_clears_cancellation(): void
    {
        $booking = Booking::factory()->status(BookingStatus::Cancelled)->create();

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Confirmed, User::factory()->admin()->create());

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->cancelled_at);
        $this->assertNull($booking->cancelled_by);
    }

    public function test_restoring_into_a_full_session_is_rejected(): void
    {
        $session = TrainingSession::factory()->create(['capacity' => 1]);
        Booking::factory()->for($session)->create();
        $cancelled = Booking::factory()->for($session)->status(BookingStatus::Cancelled)->create();

        $this->expectExceptionObject(BookingException::sessionFull());

        app(ChangeBookingStatus::class)->handle($cancelled, BookingStatus::Confirmed, User::factory()->admin()->create());
    }

    public function test_same_status_records_nothing(): void
    {
        $booking = Booking::factory()->create();

        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Confirmed, User::factory()->admin()->create());

        $this->assertSame(0, $booking->statusChanges()->count());
    }
}
