<?php

namespace App\Livewire\Forms;

use App\Models\Service;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int|string|null $duration_min = 60;

    public string $price = '';

    public int|string|null $capacity = 1;

    public string $color = '#059669';

    public bool $is_public = true;

    public ?TemporaryUploadedFile $image = null;

    public bool $remove_image = false;

    public function setService(Service $service): void
    {
        $this->service = $service;
        $this->name = $service->name;
        $this->description = (string) $service->description;
        $this->duration_min = $service->duration_min;
        $this->price = (string) $service->price;
        $this->capacity = $service->capacity;
        $this->color = $service->color;
        $this->is_public = $service->is_public;
        $this->image = null;
        $this->remove_image = false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_public' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function save(): Service
    {
        $this->validate();

        $service = $this->service ?? new Service;
        $oldImage = $service->image_path;

        $service->fill([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'duration_min' => (int) $this->duration_min,
            'price' => $this->price === '' ? null : $this->price,
            'capacity' => (int) $this->capacity,
            'color' => $this->color,
            'is_public' => $this->is_public,
        ]);

        if ($this->image) {
            $service->image_path = $this->image->store('services', 'public');
        } elseif ($this->remove_image) {
            $service->image_path = null;
        }

        $service->save();

        if ($oldImage && $oldImage !== $service->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $service;
    }
}
