<?php

namespace App\Livewire\WorkoutPlans;

use App\Actions\Workouts\AssignWorkoutPlan;
use App\Models\Exercise;
use App\Models\PlanDay;
use App\Models\PlanExercise;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Plan builder: days, exercises and their sets / reps / weight / rest, edited in place.
 */
class Edit extends Component
{
    public WorkoutPlan $plan;

    public string $name = '';

    public string $description = '';

    /**
     * Editable values per plan exercise id.
     *
     * @var array<int, array{sets: ?string, reps: ?string, weight: ?string, rest_sec: ?string, notes: ?string}>
     */
    public array $fields = [];

    /** @var array<int, string> day names per day id */
    public array $dayNames = [];

    /** @var array<int, ?string> exercise picked to add, per day id */
    public array $newExercise = [];

    public ?int $assignClientId = null;

    public function mount(WorkoutPlan $plan): void
    {
        abort_unless($plan->trainer_id === auth()->id(), 404);

        if ($plan->client_id) {
            $this->authorize('view', $plan->loadMissing('client')->client);
        }

        $this->plan = $plan;
        $this->name = $plan->name;
        $this->description = (string) $plan->description;
        $this->loadFields();
    }

    /**
     * @return Collection<int, PlanDay>
     */
    #[Computed]
    public function days(): Collection
    {
        return $this->plan->days()->with('exercises.exercise')->get();
    }

    /**
     * @return Collection<int, Exercise>
     */
    #[Computed]
    public function exerciseOptions(): Collection
    {
        return Exercise::query()->orderBy('name')->get(['id', 'name', 'muscle_group']);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function clients(): Collection
    {
        return User::query()->clients()->active()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']);
    }

    public function saveDetails(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->plan->update(['name' => $this->name, 'description' => $this->description ?: null]);

        $this->dispatch('notify', message: 'Αποθηκεύτηκε.');
    }

    public function addDay(): void
    {
        $position = (int) $this->plan->days()->max('position') + 1;

        $this->plan->days()->create(['name' => 'Ημέρα '.$position, 'position' => $position]);
        $this->refreshPlan();
    }

    public function updatedDayNames(mixed $value, int|string $dayId): void
    {
        $this->validate(["dayNames.{$dayId}" => ['required', 'string', 'max:100']], attributes: ["dayNames.{$dayId}" => 'όνομα ημέρας']);

        $this->findDay((int) $dayId)->update(['name' => $value]);
    }

    public function deleteDay(int $dayId): void
    {
        $this->findDay($dayId)->delete();
        $this->refreshPlan();
    }

    public function addExercise(int $dayId): void
    {
        $this->validate(
            ["newExercise.{$dayId}" => ['required', Rule::exists('exercises', 'id')->whereNull('deleted_at')]],
            attributes: ["newExercise.{$dayId}" => 'άσκηση'],
        );

        $day = $this->findDay($dayId);

        $day->exercises()->create([
            'exercise_id' => $this->newExercise[$dayId],
            'sets' => 3,
            'reps' => '10',
            'rest_sec' => 90,
            'position' => (int) $day->exercises()->max('position') + 1,
        ]);

        unset($this->newExercise[$dayId]);
        $this->refreshPlan();
    }

    /**
     * Saves a single edited value, e.g. key "12.reps".
     */
    public function updatedFields(mixed $value, string $key): void
    {
        [$planExerciseId, $field] = explode('.', $key, 2);

        $rules = [
            'sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reps' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'rest_sec' => ['nullable', 'integer', 'min:0', 'max:900'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        abort_unless(array_key_exists($field, $rules), 422);

        $this->validate(["fields.{$key}" => $rules[$field]], attributes: ["fields.{$key}" => match ($field) {
            'sets' => 'σετ',
            'reps' => 'επαναλήψεις',
            'weight' => 'κιλά',
            'rest_sec' => 'διάλειμμα',
            'notes' => 'σημείωση',
        }]);

        $this->findPlanExercise((int) $planExerciseId)->update([$field => $value === '' ? null : $value]);
    }

    public function removeExercise(int $planExerciseId): void
    {
        $this->findPlanExercise($planExerciseId)->delete();
        $this->refreshPlan();
    }

    /**
     * Swaps an exercise with its neighbour in the same day.
     */
    public function moveExercise(int $planExerciseId, string $direction): void
    {
        $planExercise = $this->findPlanExercise($planExerciseId);
        $siblings = $planExercise->planDay->exercises()->get()->values();
        $index = $siblings->search(fn (PlanExercise $item) => $item->is($planExercise));
        $neighbour = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);

        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($siblings, $index, $neighbour, $direction): void {
            $reordered = $siblings->all();
            $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;
            [$reordered[$index], $reordered[$neighbourIndex]] = [$neighbour, $reordered[$index]];

            foreach (array_values($reordered) as $position => $item) {
                $item->update(['position' => $position + 1]);
            }
        });

        $this->refreshPlan();
    }

    public function assign(AssignWorkoutPlan $assignWorkoutPlan): void
    {
        abort_unless($this->plan->isTemplate(), 404);

        $this->validate(['assignClientId' => ['required', Rule::in($this->clients->modelKeys())]], attributes: ['assignClientId' => 'πελάτης']);

        $copy = $assignWorkoutPlan->handle($this->plan, User::query()->findOrFail($this->assignClientId), auth()->user());

        session()->flash('status', 'Το πρόγραμμα ανατέθηκε.');
        $this->redirectRoute('workout-plans.edit', $copy, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.workout-plans.edit')->title($this->plan->name);
    }

    private function refreshPlan(): void
    {
        unset($this->days);
        $this->loadFields();
    }

    private function loadFields(): void
    {
        $this->fields = [];
        $this->dayNames = [];

        foreach ($this->days as $day) {
            $this->dayNames[$day->id] = $day->name;

            foreach ($day->exercises as $planExercise) {
                $this->fields[$planExercise->id] = [
                    'sets' => $planExercise->sets !== null ? (string) $planExercise->sets : '',
                    'reps' => (string) $planExercise->reps,
                    'weight' => $planExercise->weight !== null ? rtrim(rtrim($planExercise->weight, '0'), '.') : '',
                    'rest_sec' => $planExercise->rest_sec !== null ? (string) $planExercise->rest_sec : '',
                    'notes' => (string) $planExercise->notes,
                ];
            }
        }
    }

    private function findDay(int $dayId): PlanDay
    {
        return $this->plan->days()->findOrFail($dayId);
    }

    private function findPlanExercise(int $planExerciseId): PlanExercise
    {
        return PlanExercise::query()
            ->whereHas('planDay', fn ($query) => $query->where('workout_plan_id', $this->plan->id))
            ->with('planDay')
            ->findOrFail($planExerciseId);
    }
}
