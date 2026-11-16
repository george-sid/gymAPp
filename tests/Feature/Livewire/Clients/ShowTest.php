<?php

namespace Tests\Feature\Livewire\Clients;

use App\Enums\BookingStatus;
use App\Livewire\Clients\Show;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_client_details_and_private_notes_to_their_trainer(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client($admin)->create(['name' => 'Κατερίνα Λάμπρου']);
        $client->clientProfile->update(['medical_notes' => 'Πρόβλημα στη μέση']);

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Κατερίνα Λάμπρου')
            ->assertSee('Πρόβλημα στη μέση');
    }

    public function test_bookings_tab_lists_upcoming_and_past_bookings_with_attendance(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client($admin)->create();
        $past = TrainingSession::factory()->for(Service::factory()->create(['name' => 'Παλιό']))->at(now()->subWeek()->format('Y-m-d').' 10:00')->create();
        $future = TrainingSession::factory()->for(Service::factory()->create(['name' => 'Επόμενο']))->at(now()->addWeek()->format('Y-m-d').' 10:00')->create();
        Booking::factory()->for($past)->for($client, 'client')->status(BookingStatus::Completed)->create();
        Booking::factory()->for($future)->for($client, 'client')->create();

        $component = Livewire::actingAs($admin)
            ->test(Show::class, ['client' => $client])
            ->set('tab', 'bookings')
            ->assertSee('Επόμενο')
            ->assertSee('Παλιό');

        $this->assertSame(['completed' => 1], $component->instance()->attendance);
        $this->assertCount(1, $component->instance()->upcomingBookings);
    }

    public function test_trainer_cannot_view_another_trainers_client(): void
    {
        $otherClient = User::factory()->client()->create();

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('clients.show', $otherClient))
            ->assertForbidden();
    }
}
