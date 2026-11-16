<?php

namespace App\Livewire\Forms;

use App\Actions\Clients\SaveClient;
use App\Enums\Gender;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ClientForm extends Form
{
    public ?User $client = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $is_active = true;

    public string $birth_date = '';

    public string $gender = '';

    public string $goals = '';

    public string $medical_notes = '';

    public function setClient(User $client): void
    {
        $this->client = $client;
        $profile = $client->clientProfile;

        $this->name = $client->name;
        $this->email = (string) $client->email;
        $this->phone = (string) $client->phone;
        $this->is_active = $client->is_active;
        $this->birth_date = (string) $profile?->birth_date?->format('Y-m-d');
        $this->gender = (string) $profile?->gender?->value;
        $this->goals = (string) $profile?->goals;
        $this->medical_notes = (string) $profile?->medical_notes;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->client?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'goals' => ['nullable', 'string', 'max:2000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function save(SaveClient $saveClient, User $trainer): User
    {
        $validated = $this->validate();

        $data = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        return $this->client = $saveClient->handle($data, $trainer, $this->client);
    }
}
