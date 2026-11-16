<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Authorizes staff actions on client accounts. Admins manage every client;
 * a trainer manages only the clients assigned to them.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, User $model): bool
    {
        return $this->managesClient($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, User $model): bool
    {
        return $this->managesClient($user, $model);
    }

    private function managesClient(User $staff, User $client): bool
    {
        if (! $staff->isStaff() || $client->role !== UserRole::Client) {
            return false;
        }

        if ($staff->role === UserRole::Admin) {
            return true;
        }

        return $client->loadMissing('clientProfile')->clientProfile?->trainer_id === $staff->id;
    }
}
