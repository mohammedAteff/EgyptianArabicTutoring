<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;

class StudentTeachingPolicy
{
    public function manageTeaching(Administrator|Student $actor, Student $student): bool
    {
        return $actor instanceof Administrator && $actor->isAdmin() && ! $actor->trashed() && ! $actor->suspended_at
            && ! $student->trashed() && ! $student->merged_into_student_id;
    }

    public function usePortal(Student $student): bool
    {
        return ! $student->trashed() && ! $student->suspended_at && ! $student->merged_into_student_id && $student->identity_status === 'verified';
    }
}
