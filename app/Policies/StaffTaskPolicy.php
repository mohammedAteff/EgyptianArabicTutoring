<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffTask;

class StaffTaskPolicy
{
    public function viewAny(Administrator $user): bool
    {
        return $user->isAdmin() || $user->isAssistant();
    }

    public function create(Administrator $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Administrator $user, StaffTask $task): bool
    {
        return $user->isAdmin() || ($user->isAssistant() && (int) $task->assignee_id === (int) $user->id);
    }
}
