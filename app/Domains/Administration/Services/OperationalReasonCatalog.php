<?php

namespace App\Domains\Administration\Services;

use App\Domains\Financial\Models\ReasonCode;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperationalReasonCatalog
{
    /** @return array<string, string> */
    public static function all(): array
    {
        $default = [
            'schedule_change' => 'Schedule change',
            'holiday' => 'Holiday / travel',
            'illness' => 'Illness',
            'student_request' => 'Student request',
            'tutor_request' => 'Tutor request',
            'missed_lesson' => 'Missed lesson',
            'continuing_study' => 'Continuing study',
            'taking_break' => 'Taking a break',
            'course_finished' => 'Course finished',
            'other' => 'Other',
        ];

        try {
            if (Schema::hasTable('reason_codes')) {
                $dbReasons = ReasonCode::query()->active()->pluck('label', 'code')->toArray();
                if (! empty($dbReasons)) {
                    return array_merge($default, $dbReasons);
                }
            }
        } catch (\Throwable) {
            // Fall back to default in case of DB offline or pre-migration
        }

        return $default;
    }

    /** @return array<string, string> */
    public static function forCategory(string $category): array
    {
        try {
            if (Schema::hasTable('reason_codes')) {
                $dbReasons = ReasonCode::query()->active()->category($category)->pluck('label', 'code')->toArray();
                if (! empty($dbReasons)) {
                    return $dbReasons;
                }
            }
        } catch (\Throwable) {
            // Fall back
        }

        return self::all();
    }

    public static function validate(?string $code): ?string
    {
        if ($code !== null && ! array_key_exists($code, self::all())) {
            throw ValidationException::withMessages(['reason_code' => 'Choose a listed reason.']);
        }

        return $code;
    }
}
