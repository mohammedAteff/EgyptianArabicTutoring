<?php

namespace App\Http\Controllers\Student;

use App\Domains\Reporting\Services\StudentInsightsContext;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsightsController extends Controller
{
    public function index(Request $request, StudentInsightsReadModel $read): Response
    {
        $student = $request->attributes->get('student');
        $context = StudentInsightsContext::fromRequest($request, $student);

        return response()->view('student.insights.index', $read->page($context, $student) + ['admin' => false, 'subject' => $student, 'baseRoute' => 'student.insights.index'])->header('Cache-Control', 'private, no-store');
    }
}
