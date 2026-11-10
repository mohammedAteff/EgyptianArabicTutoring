<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\StudentInsightsContext;
use App\Domains\Reporting\Services\StudentInsightsExport;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StudentInsightsController extends Controller
{
    private function subject(Request $request, StudentInsightsReadModel $read): ?Student
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator && Administrator::query()->whereKey($actor->id)->whereNull('suspended_at')->whereIn('role', ['admin', 'super_admin'])->exists(), 403);
        $data = $request->validate(['student_id' => ['nullable', 'integer', 'min:1']]);

        return isset($data['student_id']) ? $read->population((int) $data['student_id'])->firstOrFail(['id', 'first_name', 'last_name', 'preferred_timezone', 'identity_status', 'suspended_at', 'merged_into_student_id']) : null;
    }

    public function index(Request $request, StudentInsightsReadModel $read): Response
    {
        $subject = $this->subject($request, $read);
        $context = StudentInsightsContext::fromRequest($request, $subject, true);
        $data = $read->page($context, $subject);
        $data += $subject ? ['records' => $read->contributions($context)->paginate(20, ['*'], 'records_page')->withQueryString()] : $read->students($context, (string) $request->input('q', ''));

        return response()->view('admin.student-insights.index', $data + ['admin' => true, 'subject' => $subject, 'baseRoute' => 'admin.student-insights.index', 'population' => $read->population()->count()])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, StudentInsightsReadModel $read, StudentInsightsExport $exports): Response
    {
        $subject = $this->subject($request, $read);
        $context = StudentInsightsContext::fromRequest($request, $subject, true);
        $dataset = $request->input('dataset', $subject ? 'individual_summary' : 'aggregate_summary');
        if (($dataset === 'individual_summary' && ! $subject) || ($dataset === 'aggregate_summary' && $subject)) {
            throw ValidationException::withMessages(['dataset' => 'Choose a summary matching the selected student scope.']);
        }

        try {
            return $exports->download($context, $subject, $dataset, $request->input('format', 'csv'));
        } catch (ValidationException $exception) {
            if ($request->expectsJson() || ! isset($exception->errors()['format'])) {
                throw $exception;
            }

            return redirect()->route('admin.student-insights.index', array_filter($context->filters()))->withErrors($exception->errors());
        }
    }
}
