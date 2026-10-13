<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentGuardian;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentGuardianController extends Controller
{
    public function store(Request $request, Student $student, AuditLogService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
            'is_billing_contact' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $guardian = $student->guardians()->create([
            'name' => $validated['name'],
            'relationship' => $validated['relationship'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'is_billing_contact' => $request->boolean('is_billing_contact'),
            'notes' => $validated['notes'] ?? null,
        ]);

        $audit->log('student_guardian_added', StudentGuardian::class, $guardian->id, null, [
            'student_id' => $student->id,
            'name' => $guardian->name,
            'relationship' => $guardian->relationship,
        ], $request->user('web')->id);

        return back()->with('success', 'Parent or guardian record added.');
    }

    public function destroy(Student $student, StudentGuardian $guardian, AuditLogService $audit): RedirectResponse
    {
        abort_unless((int) $guardian->student_id === (int) $student->id, 404);

        $guardianData = $guardian->only(['id', 'name', 'relationship']);
        $guardian->delete();

        $audit->log('student_guardian_removed', StudentGuardian::class, $guardianData['id'], $guardianData, null, auth('web')->id());

        return back()->with('success', 'Parent or guardian record removed.');
    }
}
