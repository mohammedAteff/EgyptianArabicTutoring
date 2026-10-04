<?php

namespace App\Policies;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Students\Models\Student;

class LessonWorkspacePolicy
{
    public function manageLessonWorkspace(Administrator|Student $user, Booking $booking): bool
    {
        return $user instanceof Administrator && $user->isAdmin() && ! $user->suspended_at && ! $user->trashed() && ! $booking->trashed();
    }

    public function viewLessonWorkspace(Administrator|Student $user, Booking $booking): bool
    {
        if ($user instanceof Administrator) {
            return $this->manageLessonWorkspace($user, $booking);
        }

        return ! $booking->trashed() && ! $user->trashed() && ! $user->suspended_at && $user->identity_status === 'verified'
            && (int) $booking->student_id === (int) $user->id
            && ($booking->status === 'completed' || ($booking->status === 'confirmed' && $booking->end_at_utc->lte(now('UTC'))));
    }
}
