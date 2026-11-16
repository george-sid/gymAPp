<?php

namespace Tests\Feature\Livewire\Clients;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_trainer_cannot_view_another_trainers_client(): void
    {
        $otherClient = User::factory()->client()->create();

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('clients.show', $otherClient))
            ->assertForbidden();
    }
}
