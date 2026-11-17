<?php

namespace Tests\Feature\Livewire\Services;

use App\Livewire\Services\Index;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_services(): void
    {
        Service::factory()->create(['name' => 'Personal 1:1']);
        Service::factory()->group()->create(['name' => 'Group HIIT']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('Personal 1:1')
            ->assertSee('Group HIIT')
            ->assertSee('έως 6 άτομα');
    }

    public function test_creates_group_service_with_image(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', 'Semi-personal')
            ->set('form.duration_min', 45)
            ->set('form.price', '20.50')
            ->set('form.capacity', 3)
            ->set('form.color', '#2563eb')
            ->set('form.image', UploadedFile::fake()->image('cover.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $service = Service::query()->sole();

        $this->assertSame('Semi-personal', $service->name);
        $this->assertSame(45, $service->duration_min);
        $this->assertSame('20.50', $service->price);
        $this->assertSame(3, $service->capacity);
        Storage::disk('public')->assertExists($service->image_path);
    }

    public function test_rejects_invalid_values(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', '')
            ->set('form.duration_min', 0)
            ->set('form.capacity', 0)
            ->set('form.color', 'red')
            ->call('save')
            ->assertHasErrors([
                'form.name' => 'required',
                'form.duration_min' => 'min',
                'form.capacity' => 'min',
                'form.color' => 'regex',
            ]);
    }

    public function test_cleared_number_fields_show_required_errors(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', 'Personal')
            ->set('form.duration_min', '')
            ->set('form.capacity', '')
            ->call('save')
            ->assertHasErrors(['form.duration_min' => 'required', 'form.capacity' => 'required']);
    }

    public function test_updates_service_and_replaces_old_image(): void
    {
        Storage::fake('public');
        $oldPath = UploadedFile::fake()->image('old.jpg')->store('services', 'public');
        $service = Service::factory()->create(['image_path' => $oldPath]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('edit', $service->id)
            ->assertSet('form.name', $service->name)
            ->set('form.name', 'Μετονομασία')
            ->set('form.image', UploadedFile::fake()->image('new.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $service->refresh();
        $this->assertSame('Μετονομασία', $service->name);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($service->image_path);
    }

    public function test_toggles_visibility_for_clients(): void
    {
        $service = Service::factory()->create(['is_public' => true]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('toggleVisibility', $service->id);

        $this->assertFalse($service->refresh()->is_public);
    }

    public function test_delete_keeps_the_service_row_for_history(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('delete', $service->id);

        $this->assertSoftDeleted($service);
    }
}
