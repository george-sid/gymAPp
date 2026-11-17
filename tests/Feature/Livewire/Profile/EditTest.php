<?php

namespace Tests\Feature\Livewire\Profile;

use App\Livewire\Profile\Edit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_and_is_linked_from_the_sidebar(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Αλλαγή κωδικού')
            ->assertSee(route('profile.edit'));
    }

    public function test_updates_own_details(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('name', 'Γιώργος')
            ->set('email', 'trainer@example.com')
            ->set('phone', '')
            ->call('updateDetails')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertSame('Γιώργος', $admin->name);
        $this->assertSame('trainer@example.com', $admin->email);
        $this->assertNull($admin->phone);
    }

    public function test_email_is_required_and_unique(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('email', 'taken@example.com')
            ->call('updateDetails')
            ->assertHasErrors(['email' => 'unique'])
            ->set('email', '')
            ->call('updateDetails')
            ->assertHasErrors(['email' => 'required']);
    }

    public function test_changes_password_and_signs_out_other_devices(): void
    {
        $admin = User::factory()->admin()->create();
        DB::table('sessions')->insert([
            'id' => 'other-device',
            'user_id' => $admin->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('current_password', 'password')
            ->set('password', 'new-secret-123')
            ->set('password_confirmation', 'new-secret-123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $this->assertTrue(Hash::check('new-secret-123', $admin->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Edit::class)
            ->set('current_password', 'wrong')
            ->set('password', 'new-secret-123')
            ->set('password_confirmation', 'new-secret-123')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password']);

        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Edit::class)
            ->set('current_password', 'password')
            ->set('password', 'short')
            ->set('password_confirmation', 'different')
            ->call('updatePassword')
            ->assertHasErrors(['password']);
    }
}
