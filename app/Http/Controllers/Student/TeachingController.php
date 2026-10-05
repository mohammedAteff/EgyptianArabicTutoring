<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Services\StudentPortalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeachingController extends Controller
{
    public function index(Request $request, StudentPortalService $portal): Response
    {
        return response()->view('student.teaching', $portal->data($request))->header('Cache-Control', 'private, no-store');
    }
}
