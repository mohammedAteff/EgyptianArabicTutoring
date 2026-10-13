<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentPortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StudentPortalPreviewController extends Controller
{
    public function show(Request $request, Student $student, StudentPortalService $portal): Response
    {
        Gate::authorize('view', $student);

        // Populate request attribute with target student
        $request->attributes->set('student', $student);

        $data = $portal->data($request);
        $data['isPreview'] = true;
        $data['previewStudent'] = $student;
        $data['returnUrl'] = route('admin.students.show', $student);

        return response()->view('student.dashboard', $data)
            ->header('Cache-Control', 'private, no-store');
    }
}
