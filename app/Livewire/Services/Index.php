<?php

namespace App\Livewire\Services;

use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Προγράμματα')]
class Index extends Component
{
    use WithFileUploads;

    public ServiceForm $form;

    public bool $showForm = false;

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->orderBy('name')->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $this->resetValidation();
        $this->form->setService($service);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form->save();

        $this->showForm = false;
        $this->form->reset();
        unset($this->services);

        $this->dispatch('notify', message: 'Το πρόγραμμα αποθηκεύτηκε.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->form->reset();
    }

    public function toggleVisibility(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $service->update(['is_public' => ! $service->is_public]);
        unset($this->services);
    }

    public function delete(int $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId);
        $service->delete();
        unset($this->services);

        $this->dispatch('notify', message: 'Το πρόγραμμα διαγράφηκε.');
    }

    public function render(): View
    {
        return view('livewire.services.index');
    }
}
