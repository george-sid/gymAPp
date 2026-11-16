<?php

namespace Tests\Feature\Livewire\Exercises;

use App\Enums\MuscleGroup;
use App\Livewire\Exercises\Index;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_exercises(): void
    {
        Exercise::factory()->create(['name' => 'Καθίσματα']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('exercises.index'))
            ->assertOk()
            ->assertSee('Καθίσματα');
    }

    public function test_creates_exercise(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', 'Πιέσεις πάγκου')
            ->set('form.muscle_group', MuscleGroup::Chest->value)
            ->set('form.equipment', 'Μπάρα')
            ->set('form.video_url', 'https://www.youtube.com/watch?v=abc')
            ->call('save')
            ->assertHasNoErrors();

        $exercise = Exercise::query()->sole();
        $this->assertSame(MuscleGroup::Chest, $exercise->muscle_group);
        $this->assertSame('https://www.youtube.com/watch?v=abc', $exercise->video_url);
    }

    public function test_rejects_non_http_video_link(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', 'Άσκηση')
            ->set('form.video_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('form.video_url');
    }

    public function test_filters_by_name_and_muscle_group(): void
    {
        Exercise::factory()->create(['name' => 'Κωπηλατική', 'muscle_group' => MuscleGroup::Back]);
        Exercise::factory()->create(['name' => 'Βυθίσεις', 'muscle_group' => MuscleGroup::Arms]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('muscleGroup', MuscleGroup::Back->value)
            ->assertSee('Κωπηλατική')
            ->assertDontSee('Βυθίσεις')
            ->set('muscleGroup', '')
            ->set('search', 'Βυθ')
            ->assertSee('Βυθίσεις')
            ->assertDontSee('Κωπηλατική');
    }

    public function test_delete_keeps_exercise_for_existing_plans(): void
    {
        $exercise = Exercise::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('delete', $exercise->id);

        $this->assertSoftDeleted($exercise);
    }
}
