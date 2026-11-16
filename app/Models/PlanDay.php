<?php

namespace App\Models;

use Database\Factories\PlanDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workout_plan_id', 'name', 'position'])]
class PlanDay extends Model
{
    /** @use HasFactory<PlanDayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkoutPlan, $this>
     */
    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    /**
     * @return HasMany<PlanExercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(PlanExercise::class)->orderBy('position')->orderBy('id');
    }
}
