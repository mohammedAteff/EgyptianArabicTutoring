<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Domains\Reporting\Services\TutorInsightsContext;
use App\Domains\Reporting\Services\TutorInsightsExport;
use App\Domains\Reporting\Services\TutorInsightsReadModel;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TutorInsightsController extends Controller
{
    private function authorizeReport(Request $request): void
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator && Administrator::query()->whereKey($actor->id)->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin'])->exists(), 403);
    }

    private function subject(Request $request, StudentInsightsReadModel $students): ?Student
    {
        $this->authorizeReport($request);
        $data = $request->validate(['student_id' => ['nullable', 'integer', 'min:1']]);

        return isset($data['student_id']) ? $students->population((int) $data['student_id'])->firstOrFail(['id', 'first_name', 'last_name']) : null;
    }

    public function index(Request $request, StudentInsightsReadModel $students, TutorInsightsReadModel $read): Response
    {
        $subject = $this->subject($request, $students);
        $context = TutorInsightsContext::fromRequest($request, $subject);
        $previous = $context->previous();
        $data = ['context' => $context, 'subject' => $subject, 'metrics' => $read->metrics($context), 'previous' => $previous, 'previousMetrics' => $read->metrics($previous)];
        if ($context->view === 'teaching') {
            $data += ['outcomes' => $read->outcomes($context), 'types' => $read->types($context), 'sessionTypes' => $read->sessionTypes(), 'trends' => $read->trends($context), 'distributions' => $read->distributions($context),
                'students' => $read->studentSummaries($context),
                'appointments' => $read->contributions($context, 'appointments')->paginate(20, ['*'], 'appointments_page')->withQueryString(),
                'upcomingCount' => $context->upcomingApplies() ? $read->appointments($context, true)->count() : null,
                'upcoming' => $read->contributions($context, 'upcoming')->paginate(20, ['*'], 'upcoming_page')->withQueryString()];
        } else {
            $data['operations'] = $read->contributions($context, 'operations')->paginate(20)->withQueryString();
        }

        return response()->view('admin.tutor-insights.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function students(Request $request, StudentInsightsReadModel $students): Response
    {
        $this->authorizeReport($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'between:1,1000000']]);
        $search = trim($data['q'] ?? '');
        $matches = $students->population()->when($search === '', fn ($query) => $query->whereRaw('1=0'))
            ->when($search !== '', fn ($query) => $query->where(fn ($names) => $names->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->when(ctype_digit($search), fn ($ids) => $ids->orWhere('id', (int) $search))))
            ->orderBy('id')->paginate(20, ['id', 'first_name', 'last_name'])->withQueryString();

        return response()->view('admin.tutor-insights.students', ['students' => $matches, 'search' => $search])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, StudentInsightsReadModel $students, TutorInsightsExport $exports): Response
    {
        $context = TutorInsightsContext::fromRequest($request, $this->subject($request, $students));
        try {
            return $exports->download($context, $request->input('dataset', 'summary') ?: 'summary', $request->input('format', 'csv') ?: 'csv');
        } catch (ValidationException $exception) {
            if ($request->expectsJson() || ! isset($exception->errors()['format'])) {
                throw $exception;
            }

            $filters = $context->filters();
            if ($request->filled('dataset')) {
                $filters['dataset'] = $request->input('dataset');
            }

            return redirect()->route('admin.tutor-insights.index', $filters)->withErrors($exception->errors());
        }
    }
}
