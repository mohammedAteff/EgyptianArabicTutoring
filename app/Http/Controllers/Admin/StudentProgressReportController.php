<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentProgressReport;
use App\Domains\Students\Services\StudentProgressReportService;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StudentProgressReportController extends Controller
{
    public function store(Request $request, Student $student, TeachingRecordService $records, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('manageTeaching', $student);
        $data = $request->validate(['date_from' => ['required', 'date_format:Y-m-d'], 'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'], 'summary' => ['required', 'string', 'max:5000'], 'next_goals' => ['nullable', 'string', 'max:5000'], 'student_visible' => ['required', 'boolean']]);
        $report = DB::transaction(function () use ($student, $data, $records, $request, $audit): StudentProgressReport {
            $student = $records->lockStudent($student->id);
            $report = StudentProgressReport::query()->create($data + ['student_id' => $student->id, 'created_by' => $request->user('web')->id]);
            $audit->log('student_progress_report_created', Student::class, $student->id, null, ['report_id' => $report->id], $request->user('web')->id);

            return $report;
        }, 3);

        return to_route('admin.students.progress-reports.show', [$student, $report]);
    }

    public function show(Student $student, StudentProgressReport $report, StudentProgressReportService $reports): Response
    {
        Gate::authorize('manageTeaching', $student);
        abort_unless((int) $report->student_id === (int) $student->id, 404);

        return response()->view('admin.students.progress-report', $reports->data($report) + ['student' => $student])->header('Cache-Control', 'private, no-store');
    }

    public function student(Request $request, int $report, StudentProgressReportService $reports): Response
    {
        $student = $request->attributes->get('student');
        $record = StudentProgressReport::query()->where('student_id', $student->id)->where('student_visible', true)->findOrFail($report);

        return response()->view('student.progress-report', $reports->data($record, true) + ['student' => $student])->header('Cache-Control', 'private, no-store');
    }
}
