<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A progress photo on the private disk; served through MeasurementPhotoController.
 */
#[Fillable(['measurement_id', 'path'])]
class MeasurementPhoto extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (MeasurementPhoto $photo) => Storage::disk('local')->delete($photo->path));
    }

    /**
     * @return BelongsTo<Measurement, $this>
     */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }
}
