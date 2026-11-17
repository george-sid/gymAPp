<?php

namespace App\Livewire\Forms;

use App\Models\Package;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PackageForm extends Form
{
    public ?Package $package = null;

    public string $name = '';

    public ?string $service_id = null;

    public int|string|null $sessions_count = 10;

    public int|string|null $valid_days = 60;

    public string $price = '';

    public bool $is_active = true;

    public function setPackage(Package $package): void
    {
        $this->package = $package;
        $this->name = $package->name;
        $this->service_id = $package->service_id ? (string) $package->service_id : null;
        $this->sessions_count = $package->sessions_count;
        $this->valid_days = $package->valid_days ?? '';
        $this->price = (string) $package->price;
        $this->is_active = $package->is_active;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'sessions_count' => ['required', 'integer', 'min:1', 'max:500'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:1095'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'service_id' => 'πρόγραμμα',
            'sessions_count' => 'συνεδρίες',
            'valid_days' => 'διάρκεια ισχύος',
        ];
    }

    public function save(): Package
    {
        $this->validate();

        $package = $this->package ?? new Package;

        $package->fill([
            'name' => $this->name,
            'service_id' => $this->service_id ?: null,
            'sessions_count' => (int) $this->sessions_count,
            'valid_days' => blank($this->valid_days) ? null : (int) $this->valid_days,
            'price' => $this->price,
            'is_active' => $this->is_active,
        ])->save();

        return $package;
    }
}
