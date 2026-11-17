<?php

namespace Tests\Feature\Livewire;

use App\Actions\Calendar\ChangeBookingStatus;
use App\Actions\Calendar\CreateBooking;
use App\Enums\BookingStatus;
use App\Livewire\Dashboard;
use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\ClientPackage;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 9 December 2026, 08:00 Athens time.
        $this->travelTo(LocalTime::toUtc('2026-12-09 08:00'));
        $this->trainer = User::factory()->admin()->create();
    }

    public function test_lists_todays_sessions_with_their_clients(): void
    {
        $client = User::factory()->client($this->trainer)->create(['name' => 'Σοφία Κ.']);
        $today = TrainingSession::factory()->for($this->trainer, 'trainer')->for(Service::factory()->create(['name' => 'Personal']))->at('2026-12-09 18:00')->create();
        Booking::factory()->for($today)->for($client, 'client')->create();
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-10 18:00')->create();
        TrainingSession::factory()->for($this->trainer, 'trainer')->cancelled()->at('2026-12-09 10:00')->create();
        TrainingSession::factory()->at('2026-12-09 11:00')->create();

        $this->actingAs($this->trainer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Σοφία Κ.')
            ->assertSee('18:00');

        $component = Livewire::actingAs($this->trainer)->test(Dashboard::class);

        $this->assertSame([$today->id], $component->instance()->todaySessions->modelKeys());
    }

    public function test_week_occupancy_compares_booked_time_with_working_hours(): void
    {
        // Monday–Friday 09:00–13:00 = 20 working hours this week.
        foreach (range(1, 5) as $weekday) {
            AvailabilityRule::factory()->for($this->trainer, 'trainer')->create(['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '13:00']);
        }

        $booked = TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-08 09:00', 120)->create();
        Booking::factory()->for($booked)->create();
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-10 09:00')->create();
        TrainingSession::factory()->for($this->trainer, 'trainer')->at('2026-12-15 09:00')->create();

        $week = Livewire::actingAs($this->trainer)->test(Dashboard::class)->instance()->week;

        $this->assertSame(2, $week['sessions']);
        $this->assertSame(120, $week['booked_minutes']);
        $this->assertSame(1200, $week['working_minutes']);
        $this->assertSame(10, $week['occupancy']);
    }

    public function test_occupancy_is_empty_without_working_hours(): void
    {
        $this->assertNull(Livewire::actingAs($this->trainer)->test(Dashboard::class)->instance()->week['occupancy']);
    }

    public function test_shows_revenue_running_out_packages_and_debts(): void
    {
        $maria = User::factory()->client($this->trainer)->create(['name' => 'Μαρία']);
        $nikos = User::factory()->client($this->trainer)->create(['name' => 'Νίκος']);
        $paid = User::factory()->client($this->trainer)->create(['name' => 'Εξοφλημένος']);

        $mariaPackage = ClientPackage::factory()->for($maria, 'client')->sessions(1)->create(['price' => 300, 'expires_on' => '2027-02-01']);
        Payment::factory()->for($maria, 'client')->create(['client_package_id' => $mariaPackage->id, 'amount' => 100, 'paid_on' => '2026-12-02']);
        Payment::factory()->for($paid, 'client')->create(['amount' => 50, 'paid_on' => '2026-11-30']);
        ClientPackage::factory()->for($paid, 'client')->create(['price' => 50, 'expires_on' => '2027-02-01']);
        Booking::factory()->for(TrainingSession::factory()->at('2026-12-01 10:00'))->for($nikos, 'client')->status(BookingStatus::Completed)->create(['charged_at' => now()]);

        $component = Livewire::actingAs($this->trainer)->test(Dashboard::class);

        $this->assertSame(100.0, $component->instance()->revenueThisMonth);
        $this->assertSame([$mariaPackage->id], $component->instance()->packagesRunningOut->modelKeys());

        $owing = $component->instance()->clientsOwing->mapWithKeys(fn (array $row) => [$row['client']->name => [$row['amount_due'], $row['owed_sessions']]])->all();
        $this->assertSame(['Μαρία' => [200.0, 0], 'Νίκος' => [0.0, 1]], $owing);
    }

    public function test_recent_activity_shows_new_bookings_and_cancellations(): void
    {
        $session = TrainingSession::factory()->for($this->trainer, 'trainer')->group()->at('2026-12-10 18:00')->create();
        $anna = User::factory()->client($this->trainer)->create(['name' => 'Άννα']);
        $nikos = User::factory()->client($this->trainer)->create(['name' => 'Νίκος']);

        app(CreateBooking::class)->handle($session, $anna, $this->trainer);
        $nikosBooking = app(CreateBooking::class)->handle($session, $nikos, $this->trainer);
        app(ChangeBookingStatus::class)->handle($nikosBooking, BookingStatus::Cancelled, $this->trainer);

        $component = Livewire::actingAs($this->trainer)->test(Dashboard::class);

        $this->assertCount(3, $component->instance()->recentActivity);
        $this->assertSame(1, $component->instance()->cancellationsLastWeek);
        $component->assertSee('Νέα κράτηση')->assertSee('Ακυρώθηκε');
    }
}
