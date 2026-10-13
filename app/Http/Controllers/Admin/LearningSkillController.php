<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\LearningSkill;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentSkillAssessment;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LearningSkillController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120', 'unique:learning_skills,label']]);
        LearningSkill::query()->create($data);

        return back()->with('success', 'Learning skill added.');
    }

    public function assess(Request $request, Student $student, TeachingRecordService $records, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('manageTeaching', $student);
        $data = $request->validate(['learning_skill_id' => ['required', 'integer'], 'booking_id' => ['nullable', 'integer'], 'state' => ['required', Rule::in(['needs_work', 'developing', 'comfortable', 'strong'])], 'notes' => ['nullable', 'string', 'max:5000'], 'student_visible' => ['required', 'boolean']]);
        DB::transaction(function () use ($student, $records, $data, $request, $audit): void {
            $student = $records->lockStudent($student->id);
            LearningSkill::query()->where('active', true)->findOrFail($data['learning_skill_id']);
            $booking = $records->lockBooking($student, $data['booking_id'] ?? null);
            $assessment = StudentSkillAssessment::query()->create($data + ['student_id' => $student->id, 'assessed_at' => now('UTC'), 'created_by' => $request->user('web')->id]);
            $audit->log('student_skill_assessed', Student::class, $student->id, null, ['assessment_id' => $assessment->id, 'booking_id' => $booking?->id], $request->user('web')->id);
        }, 3);

        return back()->with('success', 'Skill assessment saved.');
    }
}
