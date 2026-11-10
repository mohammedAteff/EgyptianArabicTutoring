<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\FeedbackContext;
use App\Domains\Students\Services\FeedbackReadModel;
use App\Domains\Students\Services\FeedbackService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackReadModel $read, private FeedbackService $actions, private FeedbackContext $contexts) {}

    public function index(Request $request): Response
    {
        $student = $request->attributes->get('student');
        $filters = $this->read->filters($request, false);
        $timezone = $this->timezone($request, $student);
        $conversations = $this->read->apply($this->read->conversations($student), $student, $filters, $timezone)->paginate(20)->appends($filters);
        $hasAny = $this->read->conversations($student)->exists();

        return response()->view('student.feedback.index', compact('conversations', 'filters', 'timezone', 'hasAny'))->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request): Response
    {
        $student = $request->attributes->get('student');
        $request->validate(['context_type' => ['nullable', Rule::in(['course', 'lesson', 'homework', 'booking'])], 'context_id' => ['nullable', 'integer', 'min:1']], [], [
            'context_type' => __('feedback.context'), 'context_id' => __('feedback.context'),
        ]);
        $context = null;
        if ($request->filled('context_type') || $request->filled('context_id')) {
            $context = $this->contexts->resolve($student, (string) $request->input('context_type'), (int) $request->input('context_id'));
            abort_unless((bool) $context, 404);
        }

        return response()->view('student.feedback.create', ['actor' => $student, 'context' => $context])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $result = $this->actions->create($request->attributes->get('student'), $request->all());
        $request->session()->flash('feedback_success', 'feedback.created');
        $url = route('student.feedback.show', ['conversation' => $result->conversation_id, 'lang' => app()->getLocale()]);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    public function show(Request $request, int $conversation): Response
    {
        $actor = $request->attributes->get('student');
        $item = $this->read->conversation($actor, $conversation);
        $data = $request->validate(['segment' => ['nullable', 'integer', 'between:1,100000']], [], ['segment' => __('feedback.history')]);
        $segment = $this->read->segment($actor, $item, isset($data['segment']) ? (int) $data['segment'] : null);
        $context = $item->context_type ? $this->contexts->resolve($actor, $item->context_type, (int) $item->context_id) : null;

        return response()->view('student.feedback.show', $segment + [
            'actor' => $actor, 'conversation' => $item, 'context' => $context,
            'timezone' => $this->timezone($request, $actor), 'admin' => false, 'baseRoute' => 'student.feedback',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $conversation): JsonResponse|RedirectResponse
    {
        $this->actions->mutate($request->attributes->get('student'), $conversation, $request->all());
        $request->session()->flash('feedback_success', 'feedback.saved');
        $url = route('student.feedback.show', ['conversation' => $conversation, 'lang' => app()->getLocale()]);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    public function acknowledge(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['receipt' => ['required', 'string', 'max:4096']], [], ['receipt' => __('feedback.history')]);

        return response()->json(['sequence' => $this->read->acknowledge($request->attributes->get('student'), $conversation, $data['receipt'])]);
    }

    public function contexts(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['course', 'lesson', 'homework', 'booking'])], 'q' => ['nullable', 'string', 'max:100']], [], [
            'type' => __('feedback.type'), 'q' => __('feedback.search'),
        ]);

        return response()->json(['items' => $this->contexts->search($request->attributes->get('student'), $data['type'], $data['q'] ?? '')]);
    }

    public function session(Request $request): JsonResponse
    {
        $student = $request->attributes->get('student');

        return response()->json([
            'actor' => 'student:'.$student->id, 'csrf' => csrf_token(),
            'proof' => Crypt::encryptString(json_encode(['student_id' => $student->id, 'session_id' => $request->session()->getId()], JSON_THROW_ON_ERROR)),
        ]);
    }

    private function timezone(Request $request, Student $student): string
    {
        $timezones = app(TimezoneService::class);
        $timezone = $request->session()->get('student_display_timezone', $student->preferred_timezone ?: $timezones->getBusinessTimezone());

        return $timezones->isValid($timezone) ? $timezone : $timezones->getBusinessTimezone();
    }
}
