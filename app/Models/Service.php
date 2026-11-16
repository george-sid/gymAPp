<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A bookable programme, e.g. "Personal 1:1" or "Group HIIT". Capacity 1 = personal.
 */
#[Fillable(['name', 'description', 'image_path', 'duration_min', 'price', 'capacity', 'color', 'is_public'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'price' => 'decimal:2',
            'capacity' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function isGroup(): bool
    {
        return $this->capacity > 1;
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path ? Storage::disk('public')->url($this->image_path) : null);
    }
}
