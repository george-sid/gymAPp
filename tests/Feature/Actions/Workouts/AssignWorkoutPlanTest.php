<?php

namespace Tests\Feature\Actions\Workouts;

use App\Actions\Workouts\AssignWorkoutPlan;
use App\Models\PlanDay;
use App\Models\PlanExercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignWorkoutPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_copies_days_and_exercises_into_a_client_plan(): void
    {
        $trainer = User::factory()->admin()->create();
        $client = User::factory()->client($trainer)->create();
        $template = WorkoutPlan::factory()->for($trainer, 'trainer')->create(['name' => 'Αρχάριοι']);
        $day = PlanDay::factory()->for($template)->create(['name' => 'Ημέρα Α', 'position' => 1]);
        PlanExercise::factory()->for($day)->create(['sets' => 4, 'reps' => '8-10', 'weight' => 40, 'position' => 1]);
        PlanExercise::factory()->for($day)->create(['position' => 2]);

        $copy = app(AssignWorkoutPlan::class)->handle($template, $client, $trainer);

        $this->assertSame($client->id, $copy->client_id);
        $this->assertSame($template->id, $copy->source_plan_id);
        $this->assertSame('Αρχάριοι', $copy->name);
        $this->assertTrue($copy->is_active);

        $copiedDay = $copy->days()->with('exercises')->sole();
        $this->assertSame('Ημέρα Α', $copiedDay->name);
        $this->assertCount(2, $copiedDay->exercises);
        $this->assertSame('8-10', $copiedDay->exercises->first()->reps);
        $this->assertSame('40.00', $copiedDay->exercises->first()->weight);
    }

    public function test_editing_the_copy_leaves_the_template_unchanged(): void
    {
        $trainer = User::factory()->admin()->create();
        $template = WorkoutPlan::factory()->for($trainer, 'trainer')->create();
        $templateExercise = PlanExercise::factory()->for(PlanDay::factory()->for($template))->create(['sets' => 3]);

        $copy = app(AssignWorkoutPlan::class)->handle($template, User::factory()->client($trainer)->create(), $trainer);
        $copy->days()->first()->exercises()->first()->update(['sets' => 5]);

        $this->assertSame(3, $templateExercise->refresh()->sets);
    }

    public function test_new_plan_replaces_the_clients_active_plan(): void
    {
        $trainer = User::factory()->admin()->create();
        $client = User::factory()->client($trainer)->create();
        $oldPlan = WorkoutPlan::factory()->for($trainer, 'trainer')->forClient($client)->create(['is_active' => true]);

        app(AssignWorkoutPlan::class)->handle(WorkoutPlan::factory()->for($trainer, 'trainer')->create(), $client, $trainer);

        $this->assertFalse($oldPlan->refresh()->is_active);
        $this->assertSame(1, WorkoutPlan::query()->where('client_id', $client->id)->where('is_active', true)->count());
    }
}
