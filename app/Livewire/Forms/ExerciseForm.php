<?php

namespace App\Livewire\Forms;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class ExerciseForm extends Form
{
    public ?Exercise $exercise = null;

    public string $name = '';

    public string $muscle_group = 'full_body';

    public string $equipment = '';

    public string $video_url = '';

    public string $instructions = '';

    public ?TemporaryUploadedFile $image = null;

    public function setExercise(Exercise $exercise): void
    {
        $this->exercise = $exercise;
        $this->name = $exercise->name;
        $this->muscle_group = $exercise->muscle_group->value;
        $this->equipment = (string) $exercise->equipment;
        $this->video_url = (string) $exercise->video_url;
        $this->instructions = (string) $exercise->instructions;
        $this->image = null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'muscle_group' => ['required', Rule::enum(MuscleGroup::class)],
            'equipment' => ['nullable', 'string', 'max:255'],
            'video_url' => ['nullable', 'url:http,https', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'muscle_group' => 'μυϊκή ομάδα',
            'equipment' => 'εξοπλισμός',
            'video_url' => 'σύνδεσμος βίντεο',
            'instructions' => 'οδηγίες',
        ];
    }

    public function save(): Exercise
    {
        $this->validate();

        $exercise = $this->exercise ?? new Exercise;
        $oldImage = $exercise->image_path;

        $exercise->fill([
            'name' => $this->name,
            'muscle_group' => $this->muscle_group,
            'equipment' => $this->equipment ?: null,
            'video_url' => $this->video_url ?: null,
            'instructions' => $this->instructions ?: null,
        ]);

        if ($this->image) {
            $exercise->image_path = $this->image->store('exercises', 'public');
        }

        $exercise->save();

        if ($oldImage && $oldImage !== $exercise->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return $exercise;
    }
}
