<?php

namespace Tests\Feature\Livewire\WorkoutPlans;

use App\Livewire\WorkoutPlans\Edit;
use App\Models\Exercise;
use App\Models\PlanDay;
use App\Models\PlanExercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private WorkoutPlan $plan;

    private PlanDay $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->admin()->create();
        $this->plan = WorkoutPlan::factory()->for($this->trainer, 'trainer')->create();
        $this->day = PlanDay::factory()->for($this->plan)->create(['name' => 'Ημέρα 1', 'position' => 1]);
    }

    public function test_builder_renders_days_and_exercises(): void
    {
        PlanExercise::factory()->for($this->day)->for(Exercise::factory()->create(['name' => 'Άρσεις θανάτου']))->create();

        $this->actingAs($this->trainer)
            ->get(route('workout-plans.edit', $this->plan))
            ->assertOk()
            ->assertSee('Άρσεις θανάτου');
    }

    public function test_adds_day_and_exercise_with_defaults(): void
    {
        $exercise = Exercise::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->call('addDay')
            ->set("newExercise.{$this->day->id}", $exercise->id)
            ->call('addExercise', $this->day->id)
            ->assertHasNoErrors();

        $this->assertSame(2, $this->plan->days()->count());
        $planExercise = $this->day->exercises()->sole();
        $this->assertSame(3, $planExercise->sets);
        $this->assertSame(90, $planExercise->rest_sec);
    }

    public function test_saves_an_edited_value_in_place(): void
    {
        $planExercise = PlanExercise::factory()->for($this->day)->create(['reps' => '10']);

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->set("fields.{$planExercise->id}.reps", '8-12')
            ->set("fields.{$planExercise->id}.weight", '42.5')
            ->set("fields.{$planExercise->id}.notes", '')
            ->assertHasNoErrors();

        $planExercise->refresh();
        $this->assertSame('8-12', $planExercise->reps);
        $this->assertSame('42.50', $planExercise->weight);
        $this->assertNull($planExercise->notes);
    }

    public function test_rejects_invalid_value_and_keeps_the_saved_one(): void
    {
        $planExercise = PlanExercise::factory()->for($this->day)->create(['sets' => 3]);

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->set("fields.{$planExercise->id}.sets", '50')
            ->assertHasErrors("fields.{$planExercise->id}.sets");

        $this->assertSame(3, $planExercise->refresh()->sets);
    }

    public function test_renames_day(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->set("dayNames.{$this->day->id}", 'Πόδια');

        $this->assertSame('Πόδια', $this->day->refresh()->name);
    }

    public function test_moves_exercise_up_within_the_day(): void
    {
        $first = PlanExercise::factory()->for($this->day)->create(['position' => 1]);
        $second = PlanExercise::factory()->for($this->day)->create(['position' => 2]);

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->call('moveExercise', $second->id, 'up');

        $this->assertSame([$second->id, $first->id], $this->day->exercises()->pluck('id')->all());
    }

    public function test_removes_exercise_and_day(): void
    {
        $planExercise = PlanExercise::factory()->for($this->day)->create();

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->call('removeExercise', $planExercise->id)
            ->call('deleteDay', $this->day->id);

        $this->assertModelMissing($planExercise);
        $this->assertModelMissing($this->day);
    }

    public function test_cannot_edit_exercise_of_another_plan(): void
    {
        $foreign = PlanExercise::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->call('removeExercise', $foreign->id)
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_assigns_template_to_client_and_opens_the_copy(): void
    {
        $client = User::factory()->client($this->trainer)->create();

        Livewire::actingAs($this->trainer)
            ->test(Edit::class, ['plan' => $this->plan])
            ->set('assignClientId', $client->id)
            ->call('assign')
            ->assertRedirect();

        $this->assertTrue(WorkoutPlan::query()->where('client_id', $client->id)->where('source_plan_id', $this->plan->id)->exists());
    }

    public function test_another_trainers_plan_is_not_found(): void
    {
        $this->actingAs(User::factory()->trainer()->create())
            ->get(route('workout-plans.edit', $this->plan))
            ->assertNotFound();
    }
}
