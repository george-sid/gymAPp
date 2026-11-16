<?php

namespace App\Models;

use Database\Factories\MeasurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client's body measurements on one day.
 */
#[Fillable(['client_id', 'measured_on', 'weight', 'body_fat', 'waist', 'chest', 'hips', 'arm', 'thigh', 'notes'])]
class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasFactory;

    /**
     * Measured values with their label and unit, in display order.
     *
     * @var array<string, array{label: string, unit: string}>
     */
    public const array METRICS = [
        'weight' => ['label' => 'Βάρος', 'unit' => 'kg'],
        'body_fat' => ['label' => 'Λίπος', 'unit' => '%'],
        'waist' => ['label' => 'Μέση', 'unit' => 'cm'],
        'chest' => ['label' => 'Στήθος', 'unit' => 'cm'],
        'hips' => ['label' => 'Περιφέρεια', 'unit' => 'cm'],
        'arm' => ['label' => 'Βραχίονας', 'unit' => 'cm'],
        'thigh' => ['label' => 'Μηρός', 'unit' => 'cm'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'weight' => 'float',
            'body_fat' => 'float',
            'waist' => 'float',
            'chest' => 'float',
            'hips' => 'float',
            'arm' => 'float',
            'thigh' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return HasMany<MeasurementPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(MeasurementPhoto::class);
    }
}
