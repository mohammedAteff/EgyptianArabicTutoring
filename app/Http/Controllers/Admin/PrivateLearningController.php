<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsTutoringAssignments;
use App\Domains\Lms\Services\LmsVideoService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLearningRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PrivateLearningController extends Controller
{
    public function __construct(private LmsTutoringAssignments $assignments, private CourseStudioService $studio, private LmsVideoService $videos) {}

    public function edit(Request $request, Student $student, Course $course, TimezoneService $timezones): Response
    {
        $course = $this->item($request, $student, $course);
        $bookingId = $this->context($request, $student);
        $graph = $this->studio->draft($this->actor($request), $course);
        $lesson = $this->lesson($graph);
        $bookings = Booking::query()->where('student_id', $student->id)->whereIn('status', ['confirmed', 'completed'])->orderByDesc('start_at_utc')->limit(100)->get();
        if ($bookingId && ! $bookings->contains('id', $bookingId)) {
            $bookings->prepend(Booking::query()->findOrFail($bookingId));
        }

        return response()->view('admin.students.private-learning', compact('student', 'course', 'graph', 'lesson', 'bookingId', 'bookings') + [
            'assets' => LmsAsset::query()->where('course_id', $course->id)->where('status', 'active')->orderBy('id')->get(),
            'videoAssets' => VideoAsset::query()->where('course_id', $course->id)->where('provider', 'bunny')->where('status', '!=', 'deleted')->orderBy('id')->get(),
            'resources' => Resource::query()->published()->orderBy('title')->limit(100)->get(['id', 'title']),
            'businessTz' => $timezones->getBusinessTimezone(),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request, Student $student, Course $course): RedirectResponse
    {
        $course = $this->item($request, $student, $course);
        $bookingId = $this->context($request, $student);
        $values = $request->validate(['operation' => ['required', Rule::in(['metadata', 'add_block', 'edit_block', 'remove_block', 'learning'])], 'version' => ['required', 'integer', 'min:1']]);
        $graph = $this->studio->draft($this->actor($request), $course);
        $lesson = $this->lesson($graph);
        $data = $request->only(['key', 'parent_key', 'kind', 'html', 'url', 'resource_id', 'asset_id', 'video_asset_id', 'definition', 'source', 'alt', 'label', 'required', 'methods', 'video_threshold']);
        if ($values['operation'] === 'metadata') {
            $request->validate(['title' => ['required', 'string', 'max:200']]);
            $data = ['title' => $request->string('title')->toString(), 'slug' => $graph['slug']];
        } elseif ($values['operation'] === 'learning') {
            abort_unless(($data['key'] ?? null) === $lesson['key'], 404);
            $data = $request->only(['key', 'required', 'methods', 'video_threshold']) + ['prerequisite_key' => null, 'drip_mode' => 'immediate'];
        } elseif ($values['operation'] === 'add_block') {
            abort_unless(($data['parent_key'] ?? null) === $lesson['key'], 404);
        } else {
            abort_unless(collect($lesson['blocks'])->contains('key', $data['key'] ?? ''), 404);
        }
        if (in_array($values['operation'], ['add_block', 'edit_block'], true)) {
            $request->validate(['kind' => ['required', Rule::in(['rich_text', 'image', 'file', 'resource', 'external_link', 'video', 'quiz', 'assignment'])]]);
            if ($data['kind'] === 'video') {
                VideoAsset::query()->where('course_id', $course->id)->where('provider', 'bunny')->where('status', 'ready')->findOrFail($data['video_asset_id'] ?? 0);
            }
        }
        $this->studio->write($this->actor($request), $course, $values['operation'], $data, (int) $values['version']);

        return $this->back($student, $course, $bookingId)->with('success', 'Private draft saved. Share below when ready.');
    }

    public function upload(Request $request, Student $student, Course $course): RedirectResponse
    {
        $course = $this->item($request, $student, $course);
        $bookingId = $this->context($request, $student);
        $values = $request->validate(['kind' => ['required', 'in:image,file'], 'file' => ['required', 'file'], 'version' => ['required', 'integer', 'min:1']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $this->studio->upload($this->actor($request), $course, $file, $values['kind'], (int) $values['version']);

        return $this->back($student, $course, $bookingId)->with('success', 'Private attachment uploaded. Choose it in content below.');
    }

    public function share(AssignLearningRequest $request, Student $student, Course $course): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        $this->assignments->sharePrivate($this->actor($request), $student, $course, $request->availability(), (int) $version);

        return to_route('admin.students.show', $student)->with('success', 'Private learning shared with this Student.');
    }

    public function video(Request $request, Student $student, Course $course): JsonResponse
    {
        $course = $this->item($request, $student, $course);
        $this->context($request, $student);
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $asset = $this->videos->createUpload($this->actor($request), $course, $request->only(['label', 'request_key']), (int) $values['version']);

        return $this->json(['asset_id' => $asset->id, 'status' => $asset->status,
            'authorization_url' => route('admin.students.private-learning.video.upload', [$student, $course, $asset]),
            'status_url' => route('admin.students.private-learning.video.status', [$student, $course, $asset])]);
    }

    public function videoUpload(Request $request, Student $student, Course $course, VideoAsset $asset): JsonResponse
    {
        $course = $this->item($request, $student, $course);
        abort_unless((int) $asset->course_id === (int) $course->id && $asset->provider === 'bunny', 404);

        return $this->json($this->videos->uploadAuthorization($this->actor($request), $course, $asset));
    }

    public function videoStatus(Request $request, Student $student, Course $course, VideoAsset $asset): JsonResponse
    {
        $course = $this->item($request, $student, $course);
        abort_unless((int) $asset->course_id === (int) $course->id && $asset->provider === 'bunny', 404);
        $asset = $this->videos->reconcile($asset);

        return $this->json($asset->only(['id', 'label', 'status', 'duration_seconds', 'failure_code']) + ['referenced' => $this->videos->hasReferences($asset)]);
    }

    private function item(Request $request, Student $student, Course $course): Course
    {
        return $this->assignments->privateItem($this->actor($request), $student, $course);
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);

        return $actor;
    }

    /** @param array<string,mixed> $graph
     * @return array<string,mixed> */
    private function lesson(array $graph): array
    {
        abort_unless(count($graph['sections']) === 1 && count($graph['sections'][0]['lessons']) === 1, 409, 'The private editor requires one learning item.');

        return $graph['sections'][0]['lessons'][0];
    }

    private function context(Request $request, Student $student): ?int
    {
        $values = $request->validate(['booking' => ['nullable', 'integer', 'min:1'], 'booking_id' => ['nullable', 'integer', 'min:1']]);
        $id = $values['booking_id'] ?? $values['booking'] ?? null;
        if ($id) {
            Booking::query()->where('student_id', $student->id)->whereIn('status', ['confirmed', 'completed'])->findOrFail($id);
        }

        return $id ? (int) $id : null;
    }

    private function back(Student $student, Course $course, ?int $bookingId): RedirectResponse
    {
        return to_route('admin.students.private-learning.edit', ['student' => $student, 'course' => $course, 'booking' => $bookingId]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
