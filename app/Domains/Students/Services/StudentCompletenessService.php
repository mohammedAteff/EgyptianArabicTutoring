<?php

namespace App\Domains\Students\Services;

use App\Domains\Forms\Services\FormCompletionReadModel;
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
        $forms = app(FormCompletionReadModel::class)->forStudent($student);
        $short = $forms->firstWhere('slug', 'short-form');
        $long = $forms->firstWhere('slug', 'long-form');
        $packages = $student->relationLoaded('packages') ? $student->packages : $student->packages()->get();
        $checks = [
            'Basic Identity' => $student->identity_status === 'verified' && filled($student->name) && filled($student->email),
            'Phone Number' => filled($student->phone),
            'Timezone' => filled($student->preferred_timezone),
            'Date of Birth' => filled($student->date_of_birth),
            'Intake Form Submission' => ($short['status'] ?? null) === 'submitted',
            'Long Form Submission' => ($long['status'] ?? null) === 'submitted',
            'Package Enrolled' => $packages->contains(fn ($package): bool => app(EntitlementService::class)->eligible($package)),
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
