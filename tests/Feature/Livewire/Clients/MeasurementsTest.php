<?php

namespace Tests\Feature\Livewire\Clients;

use App\Livewire\Clients\Measurements;
use App\Models\Measurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MeasurementsTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->trainer = User::factory()->admin()->create();
        $this->client = User::factory()->client($this->trainer)->create();
    }

    public function test_records_measurement_with_private_progress_photo(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('create')
            ->set('measured_on', '2026-11-10')
            ->set('values.weight', '82.4')
            ->set('values.waist', '91')
            ->set('photos', [UploadedFile::fake()->image('front.jpg')])
            ->call('save')
            ->assertHasNoErrors();

        $measurement = Measurement::query()->with('photos')->sole();
        $this->assertSame(82.4, $measurement->weight);
        $this->assertSame(91.0, $measurement->waist);
        $this->assertNull($measurement->body_fat);

        $photo = $measurement->photos->sole();
        Storage::disk('local')->assertExists($photo->path);
        $this->assertStringStartsWith("progress-photos/{$this->client->id}/", $photo->path);
    }

    public function test_requires_at_least_one_value_or_photo(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('create')
            ->call('save')
            ->assertHasErrors('values');

        $this->assertDatabaseCount('measurements', 0);
    }

    public function test_rejects_out_of_range_values_and_future_dates(): void
    {
        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('create')
            ->set('measured_on', now()->addWeek()->toDateString())
            ->set('values.weight', '5')
            ->set('values.body_fat', '90')
            ->call('save')
            ->assertHasErrors(['measured_on', 'values.weight', 'values.body_fat']);
    }

    public function test_chart_points_are_oldest_first_and_skip_missing_values(): void
    {
        Measurement::factory()->for($this->client, 'client')->create(['measured_on' => '2026-10-01', 'weight' => 85, 'body_fat' => null]);
        Measurement::factory()->for($this->client, 'client')->create(['measured_on' => '2026-11-01', 'weight' => 83, 'body_fat' => 22]);
        Measurement::factory()->for($this->client, 'client')->create(['measured_on' => '2026-09-01', 'weight' => null, 'body_fat' => 25]);

        $component = Livewire::actingAs($this->trainer)->test(Measurements::class, ['client' => $this->client]);

        $this->assertSame([85.0, 83.0], array_column($component->instance()->chartPoints, 'value'));

        $component->set('metric', 'body_fat');

        $this->assertSame(['01/09/2026', '01/11/2026'], array_column($component->instance()->chartPoints, 'label'));
        $component->assertSee('Μεταβολή από 01/09/2026');
    }

    public function test_edits_measurement(): void
    {
        $measurement = Measurement::factory()->for($this->client, 'client')->create(['weight' => 80]);

        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('edit', $measurement->id)
            ->assertSet('values.weight', '80')
            ->set('values.weight', '79.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(79.5, $measurement->refresh()->weight);
    }

    public function test_deleting_measurement_removes_its_photo_files(): void
    {
        $measurement = Measurement::factory()->for($this->client, 'client')->create();
        $path = UploadedFile::fake()->image('a.jpg')->store('progress-photos', 'local');
        $measurement->photos()->create(['path' => $path]);

        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('delete', $measurement->id);

        $this->assertModelMissing($measurement);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_cannot_touch_another_clients_measurement(): void
    {
        $foreign = Measurement::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Measurements::class, ['client' => $this->client])
            ->call('delete', $foreign->id)
            ->assertNotFound();
    }

    public function test_photo_is_served_to_the_trainer_and_hidden_from_others(): void
    {
        $measurement = Measurement::factory()->for($this->client, 'client')->create();
        $photo = $measurement->photos()->create(['path' => UploadedFile::fake()->image('a.jpg')->store('progress-photos', 'local')]);

        $this->actingAs($this->trainer)->get(route('measurement-photos.show', $photo))->assertOk();
        $this->actingAs(User::factory()->trainer()->create())->get(route('measurement-photos.show', $photo))->assertForbidden();
    }
}
