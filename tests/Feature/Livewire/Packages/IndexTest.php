<?php

namespace Tests\Feature\Livewire\Packages;

use App\Livewire\Packages\Index;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_packages(): void
    {
        Package::factory()->create(['name' => '10 personal']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('packages.index'))
            ->assertOk()
            ->assertSee('10 personal');
    }

    public function test_creates_service_specific_package(): void
    {
        $service = Service::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', '8 personal')
            ->set('form.service_id', (string) $service->id)
            ->set('form.sessions_count', 8)
            ->set('form.valid_days', 45)
            ->set('form.price', '240')
            ->call('save')
            ->assertHasNoErrors();

        $package = Package::query()->sole();
        $this->assertSame($service->id, $package->service_id);
        $this->assertSame(45, $package->valid_days);
        $this->assertSame('240.00', $package->price);
    }

    public function test_package_without_validity_is_saved_without_expiry(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.name', 'Χωρίς λήξη')
            ->set('form.valid_days', '')
            ->set('form.price', '100')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Package::query()->sole()->valid_days);
    }

    public function test_rejects_invalid_values(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('create')
            ->set('form.sessions_count', 0)
            ->set('form.price', '')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.sessions_count', 'form.price']);
    }

    public function test_edit_and_delete(): void
    {
        $package = Package::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Index::class)
            ->call('edit', $package->id)
            ->set('form.is_active', false)
            ->call('save')
            ->call('delete', $package->id);

        $this->assertFalse($package->refresh()->is_active);
        $this->assertSoftDeleted($package);
    }
}
