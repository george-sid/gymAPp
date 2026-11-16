<?php

namespace Tests\Feature\Actions\Calendar;

use App\Actions\Calendar\CancelTrainingSession;
use App\Enums\BookingStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\Booking;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelTrainingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_session_and_all_active_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $session = TrainingSession::factory()->group()->create();
        Booking::factory()->count(2)->for($session)->create();

        app(CancelTrainingSession::class)->handle($session, $admin);

        $this->assertSame(TrainingSessionStatus::Cancelled, $session->refresh()->status);
        $this->assertSame([BookingStatus::Cancelled], $session->bookings->pluck('status')->unique()->values()->all());
        $this->assertSame(2, $session->bookings()->where('cancelled_by', $admin->id)->count());
    }
}
