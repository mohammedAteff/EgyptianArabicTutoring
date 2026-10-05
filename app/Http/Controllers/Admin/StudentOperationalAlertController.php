<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentOperationalAlert;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentOperationalAlertController extends Controller
{
    public function store(Request $request, Student $student, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('create', StudentOperationalAlert::class);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $student, $data, $audit): void {
            Student::query()->where('identity_status', '!=', 'merged')->lockForUpdate()->findOrFail($student->id);
            $alert = StudentOperationalAlert::create([...$data, 'student_id' => $student->id, 'created_by' => $request->user('web')->id, 'updated_by' => $request->user('web')->id, 'status' => 'active']);
            $audit->log('student_operational_alert_created', StudentOperationalAlert::class, $alert->id, null, ['student_id' => $student->id, 'status' => 'active']);
        });

        return back()->with('success', 'Operational alert pinned to this student.');
    }

    public function update(Request $request, Student $student, StudentOperationalAlert $alert, AuditLogService $audit): RedirectResponse
    {
        abort_unless((int) $alert->student_id === (int) $student->id, 404);
        Gate::authorize('update', $alert);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'archived', 'resolved'])]]);
        DB::transaction(function () use ($request, $student, $alert, $data, $audit): void {
            Student::query()->where('identity_status', '!=', 'merged')->lockForUpdate()->findOrFail($student->id);
            $locked = StudentOperationalAlert::query()->where('student_id', $student->id)->lockForUpdate()->findOrFail($alert->id);
            $previous = $locked->status;
            $locked->update([...$data, 'updated_by' => $request->user('web')->id, 'resolved_at' => $data['status'] === 'resolved' ? ($locked->resolved_at ?? now('UTC')) : null]);
            $audit->log('student_operational_alert_updated', StudentOperationalAlert::class, $locked->id, ['status' => $previous], ['status' => $locked->status]);
        });

        return back()->with('success', 'Operational alert updated.');
    }
}
