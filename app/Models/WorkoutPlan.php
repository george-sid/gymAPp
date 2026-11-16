<?php

namespace App\Models;

use Database\Factories\WorkoutPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workout plan: a template (no client) or a client's own copy.
 */
#[Fillable(['trainer_id', 'client_id', 'source_plan_id', 'name', 'description', 'is_active', 'assigned_at'])]
class WorkoutPlan extends Model
{
    /** @use HasFactory<WorkoutPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assigned_at' => 'date',
        ];
    }

    public function isTemplate(): bool
    {
        return $this->client_id === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return HasMany<PlanDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(PlanDay::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<WorkoutPlan>  $query
     */
    #[Scope]
    protected function templates(Builder $query): void
    {
        $query->whereNull('client_id');
    }
}
