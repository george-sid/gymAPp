<?php

namespace App\Actions\Clients;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a client account together with its profile.
 */
class SaveClient
{
    /**
     * @param  array{name: string, email: ?string, phone: ?string, is_active: bool, birth_date: ?string, gender: ?string, goals: ?string, medical_notes: ?string}  $data
     */
    public function handle(array $data, User $trainer, ?User $client = null): User
    {
        return DB::transaction(function () use ($data, $trainer, $client): User {
            $client ??= new User;

            $client->fill(Arr::only($data, ['name', 'email', 'phone', 'is_active']));
            $client->role = UserRole::Client;
            $client->save();
            $client->loadMissing('clientProfile');

            $client->clientProfile()->updateOrCreate([], [
                ...Arr::only($data, ['birth_date', 'gender', 'goals', 'medical_notes']),
                'trainer_id' => $client->clientProfile?->trainer_id ?? $trainer->id,
            ]);

            return $client->load('clientProfile');
        });
    }
}
