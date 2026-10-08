<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\AuditLogPresentation;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\LmsOperationsService;
use App\Domains\Reporting\Services\ReportPeriod;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LearningOperationsController extends Controller
{
    public function __construct(private AnalyticsService $analytics, private LmsOperationsService $operations, private ReportPeriod $period) {}

    private function actor(Request $request, string $ability = 'viewAnalytics'): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize($ability, Course::class);

        return $actor;
    }

    /** @return array<string,mixed> */
    private function filters(Request $request, bool $dates = false): array
    {
        $rules = ['course_id' => ['nullable', 'integer', 'min:1'], 'student_id' => ['nullable', 'integer', 'min:1']];
        foreach (['page', 'courses', 'videos', 'quizzes', 'assignments'] as $name) {
            $rules[$name] = ['nullable', 'integer', 'between:1,100000'];
        }
        $filters = $dates ? $this->period->filters($request, $rules) : $request->validate($rules);
        if (! empty($filters['student_id'])) {
            Gate::forUser($this->actor($request))->authorize('manageTeaching', Student::query()->findOrFail($filters['student_id']));
        }

        return $filters;
    }

    /** @param array<string,mixed> $data */
    private function page(string $view, array $data): Response
    {
        return response()->view($view, $data + ['title' => 'Learning operations', 'courses' => Course::query()->where('status', 'published')->orderBy('title')->limit(100)->get(['id', 'title']),
            'students' => Student::verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')->orderBy('first_name')->limit(100)->get(),
            'businessTz' => app(TimezoneService::class)->getBusinessTimezone()], 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function index(Request $request): Response
    {
        $this->actor($request);
        $filters = $this->filters($request, true);
        [$start,$end] = $this->period->bounds($filters);
        $report = $this->analytics->learningReport($start, $end, $filters);
        $page = max(1, (int) $request->query('courses', 1));
        $courseRows = new LengthAwarePaginator($report['courses']->slice(($page - 1) * 20, 20)->values(), $report['courses']->count(), 20, $page,
            ['path' => $request->url(), 'pageName' => 'courses', 'query' => $request->query()]);
        $videoPage = max(1, (int) $request->query('videos', 1));
        $videoRows = new LengthAwarePaginator($report['videos']->slice(($videoPage - 1) * 20, 20)->values(), $report['videos']->count(), 20, $videoPage,
            ['path' => $request->url(), 'pageName' => 'videos', 'query' => $request->query()]);

        return $this->page('admin.lms.operations.index', ['filters' => $filters, 'summary' => $report['summary'], 'cohort' => $report['cohort'],
            'courseRows' => $courseRows, 'videoRows' => $videoRows, 'start' => $start, 'end' => $end]);
    }

    public function attention(Request $request): Response
    {
        $this->actor($request, 'viewProgress');
        $filters = $this->filters($request);

        return $this->page('admin.lms.operations.attention', ['filters' => $filters, 'attention' => $this->operations->attention($filters, max(1, (int) $request->query('page', 1)))]);
    }

    public function reviews(Request $request): Response
    {
        $this->actor($request, 'viewProgress');
        $filters = $this->filters($request);

        return $this->page('admin.lms.operations.reviews', ['filters' => $filters] + $this->operations->reviews($filters));
    }

    public function security(Request $request, AuditLogPresentation $presentation): Response
    {
        $this->actor($request, 'viewSecurity');
        $request->validate(['page' => ['nullable', 'integer', 'between:1,100000']]);
        $logs = AuditLog::query()->where('action', 'like', 'lms\_%')->orderByDesc('id')->paginate(30);
        $diffs = $logs->getCollection()->mapWithKeys(fn (AuditLog $log): array => [$log->id => $presentation->diff($log)])->all();

        return $this->page('admin.lms.operations.security', ['logs' => $logs, 'diffs' => $diffs]);
    }

    public function permissions(Request $request): Response
    {
        $this->actor($request);

        return $this->page('admin.lms.operations.permissions', []);
    }
}
