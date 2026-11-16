<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Σύνδεση');
    }

    public function test_admin_with_valid_credentials_is_logged_in_and_redirected_to_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::test(Login::class)
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::test(Login::class)
            ->set('email', $admin->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_client_account_cannot_log_in_even_with_a_password(): void
    {
        $client = User::factory()->create(['password' => Hash::make('password')]);

        Livewire::test(Login::class)
            ->set('email', $client->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        Livewire::test(Login::class)
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $admin = User::factory()->admin()->create();
        $component = Livewire::test(Login::class)->set('email', $admin->email)->set('password', 'wrong');

        foreach (range(1, 5) as $attempt) {
            $component->call('login');
        }

        $component->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
    }
}
