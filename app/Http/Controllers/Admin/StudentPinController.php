<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Students\Models\StaffPinnedStudent;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentPinController extends Controller
{
    public function toggle(Request $request, Student $student): RedirectResponse
    {
        $adminId = $request->user('web')->id;

        $existing = StaffPinnedStudent::query()
            ->where('administrator_id', $adminId)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = 'Student removed from your pinned favorites.';
        } else {
            StaffPinnedStudent::create([
                'administrator_id' => $adminId,
                'student_id' => $student->id,
                'created_at' => now(),
            ]);
            $message = 'Student pinned to your favorites.';
        }

        return back()->with('success', $message);
    }
}
