<?php

namespace Tests\Feature\Livewire\Availability;

use App\Enums\AvailabilityExceptionType;
use App\Livewire\Availability\Index;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_saved_hours(): void
    {
        $admin = User::factory()->admin()->create();
        AvailabilityRule::factory()->for($admin, 'trainer')->create(['weekday' => 2, 'start_time' => '07:00', 'end_time' => '14:00']);

        $this->actingAs($admin)
            ->get(route('availability.index'))
            ->assertOk()
            ->assertSee('Τρίτη');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSet('schedule.2', [['start' => '07:00', 'end' => '14:00']]);
    }

    public function test_saves_weekly_schedule_replacing_previous_hours(): void
    {
        $admin = User::factory()->admin()->create();
        AvailabilityRule::factory()->for($admin, 'trainer')->create(['weekday' => 6]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('schedule.6', [])
            ->set('schedule.1', [['start' => '07:00', 'end' => '14:00'], ['start' => '17:00', 'end' => '22:00']])
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $rules = AvailabilityRule::query()->where('trainer_id', $admin->id)->orderBy('start_time')->get();

        $this->assertCount(2, $rules);
        $this->assertSame([1, 1], $rules->pluck('weekday')->all());
        $this->assertSame(['07:00:00', '17:00:00'], $rules->pluck('start_time')->all());
    }

    public function test_copies_a_day_to_all_weekdays(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('schedule.1', [['start' => '08:00', 'end' => '12:00']])
            ->call('copyToWeekdays', 1)
            ->assertSet('schedule.5', [['start' => '08:00', 'end' => '12:00']])
            ->assertSet('schedule.6', []);
    }

    public function test_rejects_overlapping_hours_on_the_same_day(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('schedule.1', [['start' => '07:00', 'end' => '14:00'], ['start' => '13:00', 'end' => '18:00']])
            ->call('saveSchedule')
            ->assertHasErrors('schedule.1');

        $this->assertDatabaseCount('availability_rules', 0);
    }

    public function test_rejects_range_that_ends_before_it_starts(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('schedule.3', [['start' => '18:00', 'end' => '09:00']])
            ->call('saveSchedule')
            ->assertHasErrors('schedule.3.0.end');
    }

    public function test_adds_all_day_closure_for_a_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('exceptionType', 'closed')
            ->set('exceptionStartsOn', '2026-12-24')
            ->set('exceptionEndsOn', '2026-12-27')
            ->set('exceptionReason', 'Χριστούγεννα')
            ->call('addException')
            ->assertHasNoErrors();

        $exception = AvailabilityException::query()->sole();

        $this->assertSame(AvailabilityExceptionType::Closed, $exception->type);
        $this->assertSame('2026-12-27', $exception->ends_on->toDateString());
        $this->assertTrue($exception->isAllDay());
    }

    public function test_extra_hours_require_start_and_end_time(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('exceptionType', 'extra')
            ->set('exceptionStartsOn', '2026-12-20')
            ->call('addException')
            ->assertHasErrors(['exceptionStartTime' => 'required', 'exceptionEndTime' => 'required']);
    }

    public function test_single_day_exception_defaults_end_date_to_start_date(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->set('exceptionType', 'extra')
            ->set('exceptionStartsOn', '2026-12-20')
            ->set('exceptionStartTime', '10:00')
            ->set('exceptionEndTime', '13:00')
            ->call('addException')
            ->assertHasNoErrors();

        $this->assertSame('2026-12-20', AvailabilityException::query()->sole()->ends_on->toDateString());
    }

    public function test_cannot_delete_another_trainers_exception(): void
    {
        $exception = AvailabilityException::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('deleteException', $exception->id)
            ->assertNotFound();

        $this->assertModelExists($exception);
    }
}
