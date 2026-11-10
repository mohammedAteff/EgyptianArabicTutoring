<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackContext;
use App\Domains\Students\Services\FeedbackReadModel;
use App\Domains\Students\Services\FeedbackService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Policies\FeedbackPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackReadModel $read, private FeedbackService $actions, private FeedbackContext $contexts) {}

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator && app(FeedbackPolicy::class)->access($actor), 403);

        return $actor;
    }

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $filters = $this->read->filters($request, true);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $conversations = $this->read->apply($this->read->conversations($actor), $actor, $filters, $timezone)->with('student:id,first_name,last_name,name_normalized')->paginate(20)->appends($filters);
        $students = Student::query()->verified()->whereNull('suspended_at')->whereNull('merged_into_student_id')
            ->when(! empty($filters['student_search']), fn ($query) => $query->where('name_normalized', 'like', '%'.addcslashes(mb_strtolower($filters['student_search']), '%_\\').'%'))
            ->when(empty($filters['student_search']) && ! empty($filters['student_id']), fn ($query) => $query->whereKey($filters['student_id']))
            ->orderBy('name_normalized')->limit(20)->get(['id', 'first_name', 'last_name']);
        $hasAny = $this->read->conversations($actor)->exists();

        return response()->view('admin.feedback.index', compact('conversations', 'filters', 'timezone', 'students', 'hasAny'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $conversation): Response
    {
        $actor = $this->actor($request);
        $item = $this->read->conversation($actor, $conversation);
        $data = $request->validate(['segment' => ['nullable', 'integer', 'between:1,100000']]);
        $segment = $this->read->segment($actor, $item, isset($data['segment']) ? (int) $data['segment'] : null);
        $context = $item->context_type ? $this->contexts->resolve($item->student, $item->context_type, (int) $item->context_id) : null;
        if ($context) {
            $context['url'] = $item->context_type === 'booking' ? route('admin.bookings.show', $item->context_id) : route('admin.students.teaching', $item->student_id);
        }

        return response()->view('admin.feedback.show', $segment + [
            'actor' => $actor, 'conversation' => $item, 'context' => $context, 'admin' => true,
            'timezone' => app(TimezoneService::class)->getBusinessTimezone(), 'baseRoute' => 'admin.feedback',
            'studentReadSequence' => $this->read->studentReadSequence($item),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        $this->actions->mutate($this->actor($request), $conversation, $request->all());
        $request->session()->flash('feedback_success', 'feedback.saved');
        $url = route('admin.feedback.show', $conversation);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    public function acknowledge(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['receipt' => ['required', 'string', 'max:4096']]);

        return response()->json(['sequence' => $this->read->acknowledge($this->actor($request), $conversation, $data['receipt'])]);
    }

    public function session(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return response()->json(['actor' => 'administrator:'.$actor->id, 'csrf' => csrf_token(), 'proof' => null]);
    }
}
