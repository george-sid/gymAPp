<?php

namespace Tests\Feature\Livewire\WorkoutPlans;

use App\Livewire\WorkoutPlans\Index;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_own_templates(): void
    {
        $trainer = User::factory()->admin()->create();
        WorkoutPlan::factory()->for($trainer, 'trainer')->create(['name' => 'Δικό μου πρότυπο']);
        WorkoutPlan::factory()->for($trainer, 'trainer')->forClient(User::factory()->client($trainer)->create())->create(['name' => 'Πελάτη']);
        WorkoutPlan::factory()->create(['name' => 'Άλλου']);

        $this->actingAs($trainer)
            ->get(route('workout-plans.index'))
            ->assertOk()
            ->assertSee('Δικό μου πρότυπο')
            ->assertDontSee('Πελάτη')
            ->assertDontSee('Άλλου');
    }

    public function test_creates_template_with_a_first_day_and_opens_the_builder(): void
    {
        $trainer = User::factory()->admin()->create();

        Livewire::actingAs($trainer)
            ->test(Index::class)
            ->set('newName', 'Full Body 3×')
            ->call('createTemplate')
            ->assertRedirect();

        $plan = WorkoutPlan::query()->sole();
        $this->assertTrue($plan->isTemplate());
        $this->assertSame(1, $plan->days()->count());
    }

    public function test_cannot_delete_another_trainers_template(): void
    {
        $plan = WorkoutPlan::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('deleteTemplate', $plan->id)
            ->assertNotFound();
    }
}
