<?php

namespace App\Livewire\Clients;

use App\Models\Measurement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Measurements tab of the client card: history, chart and progress photos.
 */
class Measurements extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $clientId;

    public string $metric = 'weight';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $measured_on = '';

    /** @var array<string, string> */
    public array $values = [];

    public string $notes = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(User $client): void
    {
        $this->authorize('view', $client);

        $this->clientId = $client->id;
    }

    #[Computed]
    public function client(): User
    {
        return User::query()->findOrFail($this->clientId);
    }

    /**
     * Newest first.
     *
     * @return Collection<int, Measurement>
     */
    #[Computed]
    public function measurements(): Collection
    {
        return Measurement::query()
            ->where('client_id', $this->clientId)
            ->with('photos')
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Chart points for the selected metric, oldest first.
     *
     * @return array<int, array{timestamp: int, label: string, value: float}>
     */
    #[Computed]
    public function chartPoints(): array
    {
        $metric = array_key_exists($this->metric, Measurement::METRICS) ? $this->metric : 'weight';

        return $this->measurements
            ->filter(fn (Measurement $measurement) => $measurement->{$metric} !== null)
            ->reverse()
            ->map(fn (Measurement $measurement) => [
                'timestamp' => $measurement->measured_on->getTimestamp(),
                'label' => $measurement->measured_on->format('d/m/Y'),
                'value' => (float) $measurement->{$metric},
            ])
            ->values()
            ->all();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->measured_on = now(config('app.display_timezone'))->toDateString();
        $this->showForm = true;
    }

    public function edit(int $measurementId): void
    {
        $measurement = $this->findMeasurement($measurementId);

        $this->resetForm();
        $this->editingId = $measurement->id;
        $this->measured_on = $measurement->measured_on->toDateString();
        $this->notes = (string) $measurement->notes;

        foreach (array_keys(Measurement::METRICS) as $metric) {
            $this->values[$metric] = $measurement->{$metric} !== null ? (string) $measurement->{$metric} : '';
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('update', $this->client);

        $rules = [
            'measured_on' => ['required', 'date', 'before_or_equal:today'],
            'values.weight' => ['nullable', 'numeric', 'between:20,400'],
            'values.body_fat' => ['nullable', 'numeric', 'between:2,70'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['array', 'max:6'],
            'photos.*' => ['image', 'max:8192'],
        ];

        foreach (['waist', 'chest', 'hips', 'arm', 'thigh'] as $girth) {
            $rules["values.{$girth}"] = ['nullable', 'numeric', 'between:10,300'];
        }

        $attributes = ['measured_on' => 'ημερομηνία', 'photos.*' => 'φωτογραφία'];

        foreach (Measurement::METRICS as $metric => $definition) {
            $attributes["values.{$metric}"] = mb_strtolower($definition['label']);
        }

        $this->validate($rules, attributes: $attributes);

        $values = collect(Measurement::METRICS)
            ->keys()
            ->mapWithKeys(fn (string $metric) => [$metric => ($this->values[$metric] ?? '') === '' ? null : (float) $this->values[$metric]]);

        if ($values->filter()->isEmpty() && $this->photos === [] && ! $this->editingId) {
            throw ValidationException::withMessages(['values' => 'Συμπλήρωσε τουλάχιστον μία μέτρηση ή φωτογραφία.']);
        }

        DB::transaction(function () use ($values): void {
            $measurement = $this->editingId
                ? $this->findMeasurement($this->editingId)
                : new Measurement(['client_id' => $this->clientId]);

            $measurement->fill([
                ...$values->all(),
                'measured_on' => $this->measured_on,
                'notes' => $this->notes ?: null,
            ])->save();

            foreach ($this->photos as $photo) {
                $measurement->photos()->create([
                    'path' => $photo->store("progress-photos/{$this->clientId}", 'local'),
                ]);
            }
        });

        $this->showForm = false;
        $this->resetForm();
        unset($this->measurements, $this->chartPoints);

        $this->dispatch('notify', message: 'Η μέτρηση αποθηκεύτηκε.');
    }

    public function delete(int $measurementId): void
    {
        $this->authorize('update', $this->client);

        $measurement = $this->findMeasurement($measurementId);
        $measurement->photos->each->delete();
        $measurement->delete();

        unset($this->measurements, $this->chartPoints);
    }

    public function deletePhoto(int $photoId): void
    {
        $this->authorize('update', $this->client);

        $this->measurements->flatMap->photos->firstWhere('id', $photoId)?->delete() ?? abort(404);

        unset($this->measurements);
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function render(): View
    {
        return view('livewire.clients.measurements', [
            'metrics' => Measurement::METRICS,
        ]);
    }

    private function findMeasurement(int $measurementId): Measurement
    {
        return Measurement::query()->where('client_id', $this->clientId)->with('photos')->findOrFail($measurementId);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'measured_on', 'values', 'notes', 'photos']);
        $this->values = array_fill_keys(array_keys(Measurement::METRICS), '');
        $this->resetValidation();
    }
}
