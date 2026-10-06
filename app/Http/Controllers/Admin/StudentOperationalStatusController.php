<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentSchedulingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentOperationalStatusController extends Controller
{
    public function update(Request $request, Student $student, StudentSchedulingService $service): RedirectResponse
    {
        $values = $request->validate(['status' => ['required', 'in:active,inactive,archived'], 'reason_code' => ['nullable', 'string', 'max:40']]);
        $service->operationalStatus($student, $values['status'], $values['reason_code'] ?? null, (int) $request->user('web')->id);

        return back()->with('success', 'Operational status updated. Finance, booking history and scheduled appointments are preserved.');
    }
}
