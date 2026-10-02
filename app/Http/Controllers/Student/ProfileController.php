<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\StudentEmail;
use App\Domains\Students\Services\StudentEmailService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->attributes->get('student');

        return view('student.profile', ['student' => $student, 'emails' => StudentEmail::query()->where('student_id', $student->id)->get()]);
    }

    public function requestVerification(Request $request, StudentEmailService $emails): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $emails->requestVerification($request->attributes->get('student'), $data['email']);

        return back()->with('success', 'Verification sent. The address will be trusted only after verification.');
    }

    public function verify(Request $request, string $token, StudentEmailService $emails): RedirectResponse
    {
        $emails->verify($request->attributes->get('student'), $token);

        return redirect()->route('student.profile')->with('success', 'Secondary email verified.');
    }
}
