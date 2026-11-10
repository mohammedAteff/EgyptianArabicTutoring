<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\Student;

class FeedbackPolicy
{
    public function access(Administrator|Student $actor): bool
    {
        if ($actor instanceof Student) {
            $current = Student::query()->find($actor->id);

            return $current !== null && app(StudentTeachingPolicy::class)->usePortal($current);
        }

        return Administrator::query()->whereKey($actor->id)->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin'])->exists();
    }

    public function view(Administrator|Student $actor, FeedbackConversation $conversation): bool
    {
        return $conversation->erased_at === null && $this->access($actor)
            && ($actor instanceof Administrator || (int) $conversation->student_id === $actor->id)
            && $conversation->student !== null && app(StudentTeachingPolicy::class)->usePortal($conversation->student);
    }
}
