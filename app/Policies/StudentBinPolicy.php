<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;

class StudentBinPolicy
{
    public function view(Administrator|Student $user, StudentBin $bin): bool
    {
        return $user instanceof Administrator ? ($user->isAdmin() || $user->isAssistant()) : ((int) $bin->student_id === (int) $user->id && $bin->student_visible && $user->identity_status === 'verified' && ! $user->suspended_at);
    }

    public function update(Administrator|Student $user, StudentBin $bin): bool
    {
        if (! $this->view($user, $bin)) {
            return false;
        }

        return $user instanceof Administrator
            ? ($user->isSuperAdmin() || ($bin->created_by_type === 'staff' && (int) $bin->created_by_id === (int) $user->id))
            : ($bin->created_by_type === 'student');
    }

    public function delete(Administrator|Student $user, StudentBin $bin): bool
    {
        return $user instanceof Administrator && $user->isSuperAdmin();
    }
}
