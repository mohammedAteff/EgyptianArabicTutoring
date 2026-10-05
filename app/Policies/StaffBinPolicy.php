<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffBin;

class StaffBinPolicy
{
    public function viewAny(Administrator $user): bool
    {
        return $user->isAdmin() || $user->isAssistant();
    }

    public function create(Administrator $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Administrator $user, StaffBin $bin): bool
    {
        return $this->viewAny($user) && (int) $bin->author_id === (int) $user->id;
    }

    public function delete(Administrator $user, StaffBin $bin): bool
    {
        return $user->isSuperAdmin();
    }

    public function sharedPin(Administrator $user, StaffBin $bin): bool
    {
        return $user->isSuperAdmin();
    }

    public function personalize(Administrator $user, StaffBin $bin): bool
    {
        return $this->viewAny($user);
    }
}
