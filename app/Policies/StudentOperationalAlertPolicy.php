<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\StudentOperationalAlert;

class StudentOperationalAlertPolicy
{
    public function viewAny(Administrator $user): bool
    {
        return $user->isAdmin() || $user->isAssistant();
    }

    public function create(Administrator $user): bool
    {
        return $user->isAdmin();
    }

    public function update(Administrator $user, StudentOperationalAlert $alert): bool
    {
        return $user->isAdmin();
    }
}
