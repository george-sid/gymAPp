<?php

namespace App\Livewire\WorkoutPlans;

use App\Models\WorkoutPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Προγράμματα προπόνησης')]
class Index extends Component
{
    public string $newName = '';

    /**
     * @return Collection<int, WorkoutPlan>
     */
    #[Computed]
    public function templates(): Collection
    {
        return WorkoutPlan::query()
            ->templates()
            ->where('trainer_id', auth()->id())
            ->withCount('days')
            ->orderBy('name')
            ->get();
    }

    public function createTemplate(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:255']], attributes: ['newName' => 'όνομα']);

        $plan = WorkoutPlan::query()->create([
            'trainer_id' => auth()->id(),
            'name' => $this->newName,
        ]);
        $plan->days()->create(['name' => 'Ημέρα 1', 'position' => 1]);

        $this->redirectRoute('workout-plans.edit', $plan, navigate: true);
    }

    public function deleteTemplate(int $planId): void
    {
        WorkoutPlan::query()->templates()->where('trainer_id', auth()->id())->findOrFail($planId)->delete();
        unset($this->templates);
    }

    public function render(): View
    {
        return view('livewire.workout-plans.index');
    }
}
