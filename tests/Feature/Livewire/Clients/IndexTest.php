<?php

namespace Tests\Feature\Livewire\Clients;

use App\Livewire\Clients\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class);
    }

    public function test_lists_active_clients_but_not_staff_or_inactive_clients(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Staff Person']);
        User::factory()->client($admin)->create(['name' => 'Μαρία Παπαδοπούλου']);
        User::factory()->client($admin)->inactive()->create(['name' => 'Ανενεργός Πελάτης']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('Μαρία Παπαδοπούλου')
            ->assertDontSee('Ανενεργός Πελάτης')
            ->assertDontSee('Staff Person');
    }

    public function test_inactive_filter_shows_only_inactive_clients(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->client($admin)->create(['name' => 'Ενεργός Πελάτης']);
        User::factory()->client($admin)->inactive()->create(['name' => 'Ανενεργός Πελάτης']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('status', 'inactive')
            ->assertSee('Ανενεργός Πελάτης')
            ->assertDontSee('Ενεργός Πελάτης');
    }

    public function test_search_matches_name_phone_or_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->client($admin)->create(['name' => 'Νίκος Γεωργίου', 'phone' => '6900000001']);
        User::factory()->client($admin)->create(['name' => 'Ελένη Δήμου', 'email' => 'eleni@example.com']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('search', '6900000001')
            ->assertSee('Νίκος Γεωργίου')
            ->assertDontSee('Ελένη Δήμου')
            ->set('search', 'eleni@')
            ->assertSee('Ελένη Δήμου')
            ->assertDontSee('Νίκος Γεωργίου');
    }

    public function test_trainer_sees_only_their_own_clients(): void
    {
        $trainer = User::factory()->trainer()->create();
        User::factory()->client($trainer)->create(['name' => 'Δικός μου']);
        User::factory()->client()->create(['name' => 'Άλλου trainer']);

        Livewire::actingAs($trainer)
            ->test(Index::class)
            ->assertSee('Δικός μου')
            ->assertDontSee('Άλλου trainer');
    }
}
