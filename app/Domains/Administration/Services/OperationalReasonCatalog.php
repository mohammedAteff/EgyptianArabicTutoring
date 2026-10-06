<?php

namespace App\Domains\Administration\Services;

use Illuminate\Validation\ValidationException;

class OperationalReasonCatalog
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return ['schedule_change' => 'Schedule change', 'holiday' => 'Holiday / travel',
            'illness' => 'Illness', 'student_request' => 'Student request',
            'tutor_request' => 'Tutor request', 'missed_lesson' => 'Missed lesson',
            'continuing_study' => 'Continuing study', 'taking_break' => 'Taking a break',
            'course_finished' => 'Course finished', 'other' => 'Other'];
    }

    public static function validate(?string $code): ?string
    {
        if ($code !== null && ! array_key_exists($code, self::all())) {
            throw ValidationException::withMessages(['reason_code' => 'Choose a listed reason.']);
        }

        return $code;
    }
}
