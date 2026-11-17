<?php

namespace Tests\Feature\Livewire\Settings;

use App\Livewire\Settings\Index;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_defaults_before_anything_is_saved(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->assertSet('buffer_minutes', 10)
            ->assertSet('free_cancellation_hours', 12);
    }

    public function test_saves_settings_and_keeps_phone_as_text(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('buffer_minutes', '15')
            ->set('gym_name', 'Fit Studio')
            ->set('gym_phone', '0302101234567')
            ->call('save')
            ->assertHasNoErrors();

        $settings = new Settings;

        $this->assertSame(15, $settings->get('buffer_minutes'));
        $this->assertSame('Fit Studio', $settings->get('gym_name'));
        $this->assertSame('0302101234567', $settings->get('gym_phone'));
    }

    public function test_cleared_number_field_shows_required_error(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('buffer_minutes', '')
            ->call('save')
            ->assertHasErrors(['buffer_minutes' => 'required']);
    }

    public function test_rejects_invalid_values(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('buffer_minutes', '-5')
            ->set('slot_interval_minutes', '7')
            ->set('gym_email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['buffer_minutes', 'slot_interval_minutes', 'gym_email']);
    }
}
