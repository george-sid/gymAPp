<?php

namespace App\Livewire\Packages;

use App\Livewire\Forms\PackageForm;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Πακέτα')]
class Index extends Component
{
    public PackageForm $form;

    public bool $showForm = false;

    /**
     * @return Collection<int, Package>
     */
    #[Computed]
    public function packages(): Collection
    {
        return Package::query()->with('service')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $packageId): void
    {
        $this->resetValidation();
        $this->form->setPackage(Package::query()->findOrFail($packageId));
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form->save();
        $this->closeForm();
        unset($this->packages);

        $this->dispatch('notify', message: 'Το πακέτο αποθηκεύτηκε.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->form->reset();
    }

    public function delete(int $packageId): void
    {
        Package::query()->findOrFail($packageId)->delete();
        unset($this->packages);
    }

    public function render(): View
    {
        return view('livewire.packages.index', [
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
