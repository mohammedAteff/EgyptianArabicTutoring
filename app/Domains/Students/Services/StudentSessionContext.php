<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentSessionContext
{
    public function current(Request $request): ?Student
    {
        if (! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get('student_id');
        $expires = $request->session()->get('student_auth_expires_at');
        if (! is_numeric($id) || (int) Auth::guard('student')->id() !== (int) $id || ! is_string($expires)) {
            return null;
        }
        try {
            if (now('UTC')->gte($expires)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->find((int) $id);
    }
}
