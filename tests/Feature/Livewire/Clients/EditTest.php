<?php

namespace Tests\Feature\Livewire\Clients;

use App\Enums\Gender;
use App\Enums\UserRole;
use App\Livewire\Clients\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_client_with_profile_assigned_to_current_trainer(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('form.name', 'Γιώργος Ιωάννου')
            ->set('form.phone', '6912345678')
            ->set('form.email', 'giorgos@example.com')
            ->set('form.birth_date', '1990-05-10')
            ->set('form.gender', Gender::Male->value)
            ->set('form.goals', 'Απώλεια βάρους')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $client = User::query()->where('email', 'giorgos@example.com')->with('clientProfile')->sole();

        $this->assertSame(UserRole::Client, $client->role);
        $this->assertNull($client->password);
        $this->assertSame($admin->id, $client->clientProfile->trainer_id);
        $this->assertSame('1990-05-10', $client->clientProfile->birth_date->format('Y-m-d'));
        $this->assertSame('Απώλεια βάρους', $client->clientProfile->goals);
    }

    public function test_client_can_be_created_without_email(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('form.name', 'Χωρίς Email')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => 'Χωρίς Email', 'email' => null]);
    }

    public function test_medical_notes_are_stored_encrypted(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('form.name', 'Πελάτης')
            ->set('form.medical_notes', 'Τραυματισμός γόνατος')
            ->call('save')
            ->assertHasNoErrors();

        $stored = DB::table('client_profiles')->value('medical_notes');

        $this->assertNotSame('Τραυματισμός γόνατος', $stored);
        $this->assertSame('Τραυματισμός γόνατος', decrypt($stored, false));
    }

    public function test_name_is_required(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Edit::class)
            ->call('save')
            ->assertHasErrors(['form.name' => 'required']);
    }

    public function test_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->client($admin)->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('form.name', 'Νέος')
            ->set('form.email', 'taken@example.com')
            ->call('save')
            ->assertHasErrors(['form.email' => 'unique']);
    }

    public function test_updates_existing_client_and_keeps_own_email(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client($admin)->create(['email' => 'same@example.com']);

        Livewire::actingAs($admin)
            ->test(Edit::class, ['client' => $client])
            ->assertSet('form.email', 'same@example.com')
            ->set('form.name', 'Νέο Όνομα')
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $client->refresh();
        $this->assertSame('Νέο Όνομα', $client->name);
        $this->assertFalse($client->is_active);
        $this->assertDatabaseCount('client_profiles', 1);
    }

    public function test_trainer_cannot_edit_another_trainers_client(): void
    {
        $otherClient = User::factory()->client()->create();

        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('clients.edit', $otherClient))
            ->assertForbidden();
    }

    public function test_staff_account_cannot_be_opened_as_a_client(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('clients.edit', $admin))
            ->assertForbidden();
    }
}
