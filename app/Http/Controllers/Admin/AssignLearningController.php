<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Services\LmsSettings;
use App\Domains\Lms\Services\LmsTutoringAssignments;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLearningRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AssignLearningController extends Controller
{
    public function __construct(private LmsTutoringAssignments $assignments) {}

    public function create(Request $request, Student $student, TimezoneService $timezones): Response
    {
        $this->assignments->authorize($this->actor($request), $student);
        $filters = $request->validate(['booking' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:100']]);
        $bookings = Booking::query()->where('student_id', $student->id)->whereIn('status', ['confirmed', 'completed'])->orderByDesc('start_at_utc')->limit(100)->get();
        $bookingId = isset($filters['booking']) ? (int) $filters['booking'] : null;
        if ($bookingId) {
            $context = Booking::query()->where('student_id', $student->id)->whereIn('status', ['confirmed', 'completed'])->findOrFail($bookingId);
            if (! $bookings->contains('id', $bookingId)) {
                $bookings->prepend($context);
            }
        }
        $search = '%'.addcslashes($filters['q'] ?? '', '\\%_').'%';
        $courses = Course::query()->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->where(fn ($query) => $query->where('kind', 'catalog')->orWhere('owner_student_id', $student->id))->where('title', 'like', $search)->orderBy('title')->limit(100)->get();
        $lessons = Lesson::query()->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->whereHas('section', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC')))
            ->whereHas('course', fn ($query) => $query->where('status', 'published')->where('published_at', '<=', now('UTC'))
                ->where(fn ($query) => $query->where('kind', 'catalog')->orWhere('owner_student_id', $student->id)))
            ->where('title', 'like', $search)->with('course:id,title,kind,owner_student_id')->orderBy('title')->limit(100)->get();
        $assessments = LessonBlock::query()->whereIn('lesson_id', $lessons->pluck('id'))->where('status', 'ready')->whereIn('kind', ['quiz', 'assignment'])->with('lesson:id,title')->orderBy('id')->limit(100)->get();
        $resources = Resource::query()->published()->where('title', 'like', $search)->orderBy('title')->limit(100)->get(['id', 'title']);
        $students = Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->where('id', '!=', $student->id)->orderBy('first_name')->limit(100)->get(['id', 'first_name', 'last_name']);

        return response()->view('admin.students.assign-learning', compact('student', 'bookings', 'bookingId', 'courses', 'lessons', 'assessments', 'resources', 'students', 'filters') + ['businessTz' => $timezones->getBusinessTimezone(),
            'defaultVideoThreshold' => app(LmsSettings::class)->values()['video_threshold']])->header('Cache-Control', 'private, no-store');
    }

    public function store(AssignLearningRequest $request, Student $student): RedirectResponse
    {
        $values = $request->validate(['kind' => ['required', Rule::in(['course', 'lesson', 'resource', 'quiz', 'assignment'])], 'target_id' => ['required', 'integer', 'min:1'],
            'extra_student_ids' => ['nullable', 'array', 'max:24'], 'extra_student_ids.*' => ['required', 'integer', 'min:1', 'distinct']]);
        $data = $request->availability() + $values;
        if (! empty($values['extra_student_ids'])) {
            $ids = array_values(array_unique(array_merge([$student->id], array_map('intval', $values['extra_student_ids']))));
            $results = $this->assignments->bulk($this->actor($request), $ids, $data);
            $reused = collect($results)->where('reused', true)->count();
            $message = count($results).' Students have individual learning assignments. '.$reused.' existing assignments retained their current terms.';
        } else {
            $result = $this->assignments->assign($this->actor($request), $student, $data);
            $message = $result['reused'] ? 'This learning is already assigned for this session. Existing access and instructions were kept; use Extend or Revoke in Learning to change them.' : 'Learning assigned. It will appear for this Student when available.';
        }

        return to_route('admin.students.show', $student)->with('success', $message);
    }

    public function private(AssignLearningRequest $request, Student $student): RedirectResponse
    {
        $course = $this->assignments->createPrivate($this->actor($request), $student,
            $request->availability() + $request->only(['title', 'kind', 'html', 'definition', 'share_now']));

        return to_route('admin.students.private-learning.edit', ['student' => $student, 'course' => $course, 'booking' => $request->integer('booking_id') ?: null])
            ->with('success', $course->status === 'published' ? 'Private learning shared with this Student.' : 'Private draft created. Add content and share when ready.');
    }

    public function change(AssignLearningRequest $request, Student $student, AccessGrant $grant): RedirectResponse
    {
        $values = $request->validate(['action' => ['required', Rule::in(['revoke', 'extend', 'set_expiration', 'remove_expiration', 'set_start'])], 'version' => ['required', 'integer', 'min:1']]);
        $this->assignments->change($this->actor($request), $student, $grant, $values['action'], $request->availability(), (int) $values['version'], $request->string('request_key')->toString());

        return to_route('admin.students.show', $student)->with('success', 'Learning access updated. History is retained.');
    }

    public function withdraw(Request $request, Student $student, ResourceAssignment $assignment): RedirectResponse
    {
        $this->assignments->withdrawResource($this->actor($request), $student, $assignment);

        return to_route('admin.students.show', $student)->with('success', 'Resource sharing withdrawn. The source Resource and file are retained.');
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);

        return $actor;
    }
}
