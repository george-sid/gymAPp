<?php

namespace Tests\Feature\Actions\Packages;

use App\Actions\Packages\AssignPackage;
use App\Actions\Packages\GetClientAccount;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_the_template_and_sets_the_expiry_date(): void
    {
        $client = User::factory()->client()->create();
        $package = Package::factory()->create(['name' => '8 personal', 'sessions_count' => 8, 'valid_days' => 60, 'price' => 280]);

        $clientPackage = app(AssignPackage::class)->handle($client, $package, '2026-12-01', '250');

        $this->assertSame('8 personal', $clientPackage->name);
        $this->assertSame(8, $clientPackage->sessions_total);
        $this->assertSame('2027-01-30', $clientPackage->expires_on->toDateString());
        $this->assertSame('250.00', $clientPackage->price);

        $package->update(['sessions_count' => 20]);
        $this->assertSame(8, $clientPackage->refresh()->sessions_total);
    }

    public function test_package_without_validity_never_expires(): void
    {
        $clientPackage = app(AssignPackage::class)->handle(User::factory()->client()->create(), Package::factory()->create(['valid_days' => null]), '2026-12-01');

        $this->assertNull($clientPackage->expires_on);
    }

    public function test_new_package_covers_owed_sessions_oldest_first(): void
    {
        $client = User::factory()->client()->create();
        $owed = collect(['2026-11-03', '2026-11-01', '2026-11-02'])->map(fn (string $date) => Booking::factory()
            ->for(TrainingSession::factory()->at("{$date} 10:00"))
            ->for($client, 'client')
            ->status(BookingStatus::Completed)
            ->create(['charged_at' => now()]));

        $clientPackage = app(AssignPackage::class)->handle($client, Package::factory()->create(['sessions_count' => 2]), '2026-12-01');

        $this->assertSame(0, $clientPackage->remainingSessions());
        $this->assertSame($clientPackage->id, $owed[1]->refresh()->client_package_id);
        $this->assertSame($clientPackage->id, $owed[2]->refresh()->client_package_id);
        $this->assertTrue($owed[0]->refresh()->isOwed());
    }

    public function test_service_specific_package_covers_only_owed_sessions_of_that_service(): void
    {
        $client = User::factory()->client()->create();
        $personal = Service::factory()->create();
        $other = Booking::factory()->for(TrainingSession::factory())->for($client, 'client')->create(['charged_at' => now(), 'status' => 'completed']);

        $clientPackage = app(AssignPackage::class)->handle($client, Package::factory()->for($personal)->create(), '2026-12-01');

        $this->assertSame(10, $clientPackage->remainingSessions());
        $this->assertTrue($other->refresh()->isOwed());
    }

    public function test_account_summarises_sessions_and_money(): void
    {
        $client = User::factory()->client()->create();
        $package = ClientPackage::factory()->for($client, 'client')->sessions(2)->create(['price' => 300]);
        Payment::factory()->for($client, 'client')->create(['client_package_id' => $package->id, 'amount' => 120]);
        foreach (range(1, 3) as $day) {
            Booking::factory()->for(TrainingSession::factory()->at(now()->addDays($day)->format('Y-m-d').' 10:00'))->for($client, 'client')->create();
        }

        $account = app(GetClientAccount::class)->handle($client);

        $this->assertSame(2, $account['available_sessions']);
        $this->assertSame(3, $account['upcoming_bookings']);
        $this->assertSame(-1, $account['free_sessions']);
        $this->assertSame(180.0, $account['amount_due']);
        $this->assertNotNull(app(GetClientAccount::class)->bookingWarning($client));
    }
}
