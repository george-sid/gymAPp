<?php

namespace Tests\Feature\Actions\Packages;

use App\Actions\Calendar\CancelTrainingSession;
use App\Actions\Calendar\ChangeBookingStatus;
use App\Actions\Packages\WaiveBookingCharge;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientPackage;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyBookingChargeTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(LocalTime::toUtc('2026-12-01 09:00'));
        $this->trainer = User::factory()->admin()->create();
        $this->client = User::factory()->client($this->trainer)->create();
    }

    public function test_booking_alone_does_not_use_a_session(): void
    {
        $package = ClientPackage::factory()->for($this->client, 'client')->create();
        $this->booking('2026-12-03 18:00');

        $this->assertSame(10, $package->remainingSessions());
    }

    public function test_completed_and_no_show_use_a_session(): void
    {
        $package = ClientPackage::factory()->for($this->client, 'client')->create();

        $this->setStatus($this->booking('2026-11-28 18:00'), BookingStatus::Completed);
        $this->setStatus($this->booking('2026-11-29 18:00'), BookingStatus::NoShow);

        $this->assertSame(8, $package->remainingSessions());
    }

    public function test_correcting_attendance_back_to_confirmed_refunds_the_session(): void
    {
        $package = ClientPackage::factory()->for($this->client, 'client')->create();
        $booking = $this->booking('2026-11-28 18:00');

        $this->setStatus($booking, BookingStatus::Completed);
        $this->setStatus($booking, BookingStatus::Confirmed);

        $this->assertSame(10, $package->remainingSessions());
        $this->assertNull($booking->refresh()->charged_at);
    }

    public function test_cancellation_in_time_is_free_but_late_cancellation_is_charged(): void
    {
        // Now is 09:00; free cancellation up to 12 hours before (default setting).
        $package = ClientPackage::factory()->for($this->client, 'client')->create();

        $this->setStatus($this->booking('2026-12-01 21:00'), BookingStatus::Cancelled);
        $this->setStatus($this->booking('2026-12-02 08:00'), BookingStatus::Cancelled);

        $this->assertSame(10, $package->remainingSessions());

        $late = $this->booking('2026-12-01 20:59');
        $this->setStatus($late, BookingStatus::Cancelled);

        $this->assertSame(9, $package->remainingSessions());
        $this->assertTrue($late->refresh()->isCharged());
    }

    public function test_trainer_cancelling_the_whole_session_charges_nobody(): void
    {
        $package = ClientPackage::factory()->for($this->client, 'client')->create();
        $booking = $this->booking('2026-12-01 11:00');

        app(CancelTrainingSession::class)->handle($booking->trainingSession, $this->trainer);

        $this->assertSame(10, $package->remainingSessions());
    }

    public function test_waiving_refunds_and_unwaiving_charges_again(): void
    {
        $package = ClientPackage::factory()->for($this->client, 'client')->create();
        $booking = $this->booking('2026-11-28 18:00');
        $this->setStatus($booking, BookingStatus::NoShow);

        app(WaiveBookingCharge::class)->handle($booking);
        $this->assertSame(10, $package->remainingSessions());

        app(WaiveBookingCharge::class)->handle($booking, waive: false);
        $this->assertSame(9, $package->remainingSessions());
    }

    public function test_uses_the_package_that_expires_first(): void
    {
        $later = ClientPackage::factory()->for($this->client, 'client')->create(['expires_on' => '2027-03-01']);
        $sooner = ClientPackage::factory()->for($this->client, 'client')->create(['expires_on' => '2026-12-31']);

        $booking = $this->booking('2026-11-28 18:00');
        $this->setStatus($booking, BookingStatus::Completed);

        $this->assertSame($sooner->id, $booking->refresh()->client_package_id);
        $this->assertSame(10, $later->remainingSessions());
    }

    public function test_skips_used_up_expired_and_other_service_packages(): void
    {
        $service = Service::factory()->create();
        $usedUp = ClientPackage::factory()->for($this->client, 'client')->sessions(1)->create(['expires_on' => '2026-12-05']);
        Booking::factory()->for($this->client, 'client')->create(['client_package_id' => $usedUp->id, 'charged_at' => now()]);
        ClientPackage::factory()->for($this->client, 'client')->create(['expires_on' => '2026-11-20']);
        ClientPackage::factory()->for($this->client, 'client')->for(Service::factory()->create())->create();

        $booking = $this->booking('2026-11-28 18:00', $service);
        $this->setStatus($booking, BookingStatus::Completed);

        $booking->refresh();
        $this->assertTrue($booking->isOwed());
    }

    public function test_session_without_any_package_is_owed(): void
    {
        $booking = $this->booking('2026-11-28 18:00');

        $this->setStatus($booking, BookingStatus::Completed);

        $this->assertTrue($booking->refresh()->isOwed());
    }

    private function booking(string $localStart, ?Service $service = null): Booking
    {
        $session = TrainingSession::factory()
            ->for($this->trainer, 'trainer')
            ->for($service ?? Service::factory()->create())
            ->at($localStart)
            ->create();

        return Booking::factory()->for($session)->for($this->client, 'client')->create();
    }

    private function setStatus(Booking $booking, BookingStatus $status): void
    {
        app(ChangeBookingStatus::class)->handle($booking, $status, $this->trainer);
    }
}
