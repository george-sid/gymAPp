<?php

namespace App\Models;

use Database\Factories\PlanExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exercise inside a plan day, with sets, reps, weight and rest.
 */
#[Fillable(['plan_day_id', 'exercise_id', 'sets', 'reps', 'weight', 'rest_sec', 'notes', 'position'])]
class PlanExercise extends Model
{
    /** @use HasFactory<PlanExerciseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sets' => 'integer',
            'weight' => 'decimal:2',
            'rest_sec' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PlanDay, $this>
     */
    public function planDay(): BelongsTo
    {
        return $this->belongsTo(PlanDay::class);
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class)->withTrashed();
    }
}
