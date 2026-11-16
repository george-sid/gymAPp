<?php

namespace App\Livewire\Exercises;

use App\Enums\MuscleGroup;
use App\Livewire\Forms\ExerciseForm;
use App\Models\Exercise;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Ασκήσεις')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public ExerciseForm $form;

    public bool $showForm = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $muscleGroup = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMuscleGroup(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Exercise>
     */
    #[Computed]
    public function exercises(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Exercise::query()
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($this->muscleGroup !== '', fn ($query) => $query->where('muscle_group', $this->muscleGroup))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(30);
    }

    public function create(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $exerciseId): void
    {
        $this->resetValidation();
        $this->form->setExercise(Exercise::query()->findOrFail($exerciseId));
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form->save();
        $this->closeForm();
        unset($this->exercises);

        $this->dispatch('notify', message: 'Η άσκηση αποθηκεύτηκε.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->form->reset();
    }

    public function delete(int $exerciseId): void
    {
        Exercise::query()->findOrFail($exerciseId)->delete();
        unset($this->exercises);

        $this->dispatch('notify', message: 'Η άσκηση διαγράφηκε.');
    }

    public function render(): View
    {
        return view('livewire.exercises.index', [
            'muscleGroups' => MuscleGroup::cases(),
        ]);
    }
}
