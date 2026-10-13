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
        $defaults = match ($category) {
            'refund' => ['student_request' => 'Student request', 'tutor_cancellation' => 'Tutor cancellation', 'duplicate_payment' => 'Duplicate payment', 'pricing_correction' => 'Pricing correction', 'other' => 'Other'],
            'courtesy_credit' => ['service_recovery' => 'Service recovery', 'promotion' => 'Promotion', 'tutor_cancellation_compensation' => 'Tutor cancellation compensation', 'administrative_correction' => 'Administrative correction', 'other' => 'Other'],
            'validity_extension' => ['travel' => 'Travel', 'medical_personal_break' => 'Medical / personal break', 'tutor_availability' => 'Tutor availability', 'service_recovery' => 'Service recovery', 'administrative' => 'Administrative', 'other' => 'Other'],
            'suspension' => ['payment_overdue' => 'Payment overdue', 'conduct_issue' => 'Conduct issue', 'temporary_administrative_hold' => 'Temporary administrative hold', 'requested_by_student' => 'Requested by student', 'other' => 'Other'],
            'cancellation', 'reschedule' => ['schedule_change' => 'Schedule change', 'holiday' => 'Holiday / travel', 'illness' => 'Illness', 'student_request' => 'Student request', 'tutor_request' => 'Tutor request', 'other' => 'Other'],
            default => self::all(),
        };
        if (Schema::hasTable('reason_codes')) {
            $configured = ReasonCode::query()->category($category)->get();
            foreach ($configured as $reason) {
                unset($defaults[$reason->code]);
            }

            return $configured->where('is_active', true)->pluck('label', 'code')->all() + $defaults;
        }

        return $defaults;
    }

    public static function validate(?string $code, ?string $category = null): ?string
    {
        if ($code !== null && ! array_key_exists($code, $category ? self::forCategory($category) : self::all())) {
            throw ValidationException::withMessages(['reason_code' => 'Choose a listed reason.']);
        }

        return $code;
    }
}
