<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Services\StudentHomeReadModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    public function index(Request $request, StudentHomeReadModel $home): Response
    {
        return response()->view('student.home', $home->data($request))->header('Cache-Control', 'private, no-store');
    }
}
