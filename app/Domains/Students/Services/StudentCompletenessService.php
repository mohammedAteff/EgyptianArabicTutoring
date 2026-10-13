<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;

class StudentCompletenessService
{
    /**
     * @return array{
     *     percentage: int,
     *     completed_count: int,
     *     total_count: int,
     *     missing: array<int, string>,
     *     completed: array<int, string>,
     *     status: string,
     *     badge_class: string
     * }
     */
    public function assess(Student $student): array
    {
        $checks = [
            'Basic Identity' => ! empty($student->name) && ! empty($student->email),
            'Phone Number' => ! empty($student->phone),
            'Timezone' => ! empty($student->preferred_timezone),
            'Meeting Preference' => ! empty($student->preferred_meeting_provider_id),
            'Date of Birth' => ! empty($student->date_of_birth),
            'Intake Form Submission' => $student->relationLoaded('formSubmissions')
                ? $student->formSubmissions->isNotEmpty()
                : $student->formSubmissions()->exists(),
            'Package Enrolled' => $student->relationLoaded('packages')
                ? $student->packages->isNotEmpty()
                : $student->packages()->exists(),
        ];

        $completed = [];
        $missing = [];

        foreach ($checks as $label => $passed) {
            if ($passed) {
                $completed[] = $label;
            } else {
                $missing[] = $label;
            }
        }

        $totalCount = count($checks);
        $completedCount = count($completed);
        $percentage = (int) round(($completedCount / $totalCount) * 100);

        $status = match (true) {
            $percentage >= 85 => 'complete',
            $percentage >= 55 => 'good',
            default => 'action_needed',
        };

        $badgeClass = match ($status) {
            'complete' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'good' => 'bg-amber-100 text-amber-800 border-amber-200',
            default => 'bg-rose-100 text-rose-800 border-rose-200',
        };

        return [
            'percentage' => $percentage,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'missing' => $missing,
            'completed' => $completed,
            'status' => $status,
            'badge_class' => $badgeClass,
        ];
    }
}
