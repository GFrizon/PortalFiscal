<?php

namespace App\Policies;

use App\Models\BusinessUnit;
use App\Models\User;

class BusinessUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, BusinessUnit $businessUnit): bool
    {
        return $user->isAdmin();
    }
}
