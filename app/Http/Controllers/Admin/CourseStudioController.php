<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Models\ProtectionProfile;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsContentService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CourseStudioController extends Controller
{
    public function __construct(private CourseStudioService $studio, private LmsContentService $content, private LessonMaterialService $files) {}

    public function index(Request $request): Response
    {
        $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['draft', 'published', 'unpublished', 'archived'])],
            'view' => ['nullable', Rule::in(['active', 'archived', 'all'])], 'audience' => ['nullable', Rule::in(['public', 'member', 'all_students', 'selected_students'])]]);
        $scope = $filters['view'] ?? 'active';
        $query = Course::query()->with(['accessRule', 'revisions' => fn ($query) => $query->where('status', 'draft')->select(['id', 'revisable_type', 'revisable_id', 'title', 'revision_number', 'updated_at'])]);
        if ($scope === 'active') {
            $query->where('status', '!=', 'archived');
        } elseif ($scope === 'archived') {
            $query->where('status', 'archived');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['audience'])) {
            $query->where(fn ($query) => $query->whereHas('accessRule', fn ($rules) => $rules->where('audience', $filters['audience']))
                ->when($filters['audience'] === 'selected_students', fn ($query) => $query->orWhereDoesntHave('accessRule')));
        }
        if (! empty($filters['search'])) {
            $search = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($query) => $query->where('title', 'like', $search)->orWhere('slug', 'like', $search)
                ->orWhereHas('revisions', fn ($revisions) => $revisions->where('status', 'draft')->where('title', 'like', $search)));
        }

        return $this->view('admin.lms.courses.index', ['title' => 'Course Studio', 'courses' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create(Request $request): Response
    {
        $this->actor($request);

        return $this->view('admin.lms.courses.create', ['title' => 'Create Course', 'students' => Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->orderBy('first_name')->orderBy('id')->get(['id', 'first_name', 'last_name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['title' => ['required', 'string', 'max:200'], 'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('lms_courses', 'slug')],
            'kind' => ['required', Rule::in(['catalog', 'private'])], 'owner_student_id' => ['nullable', 'integer', 'min:1']]);
        if ($values['kind'] === 'private') {
            Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->findOrFail($values['owner_student_id'] ?? 0);
        }
        $course = $this->studio->create($actor, $values);

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'Course created as Draft. Build the sections and lessons below.');
    }

    public function edit(Request $request, Course $course): Response
    {
        $actor = $this->actor($request);
        $graph = $this->studio->draft($actor, $course);
        $ids = $this->assetIds($graph);

        return $this->view('admin.lms.courses.edit', ['title' => 'Edit Course', 'course' => $course, 'graph' => $graph,
            'resources' => Resource::query()->published()->orderBy('title')->get(['id', 'title', 'file_type', 'file_path']),
            'assets' => LmsAsset::query()->where('status', 'active')->where(fn ($query) => $query->where('course_id', $course->id)->orWhereIn('id', $ids))->orderBy('id')->get(),
            'videoAssets' => VideoAsset::query()->where(fn ($query) => $query->where('course_id', $course->id)->orWhereIn('id', $this->assetIds($graph, 'video_asset_id')))->whereNot('status', 'deleted')->orderBy('id')->get(),
            'profiles' => ProtectionProfile::query()->where('active', true)->orderBy('id')->get(),
            'hasDraft' => $course->revisions()->where('status', 'draft')->exists()]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['operation' => ['required', Rule::in(['learning', 'protection', 'metadata', 'access', 'add_section', 'edit_section', 'add_lesson', 'edit_lesson', 'move_lesson', 'add_block', 'edit_block', 'remove_block', 'reorder_section', 'reorder_lesson', 'reorder_block'])],
            'version' => ['required', 'integer', 'min:1']]);
        $data = $request->only(['definition', 'required', 'methods', 'video_threshold', 'prerequisite_key', 'drip_mode', 'drip_days', 'drip_at', 'protection_profile_id', 'video_asset_id', 'title', 'slug', 'status', 'key', 'parent_key', 'destination', 'direction', 'audience', 'access_mode', 'starts_at', 'expires_at', 'relative_days', 'kind', 'html', 'url', 'resource_id', 'asset_id', 'source', 'alt', 'label']);
        if ($values['operation'] === 'learning' && ($data['drip_mode'] ?? null) === 'fixed' && $request->filled('drip_local')) {
            $request->validate(['drip_local' => ['required', 'date_format:Y-m-d\TH:i']]);
            $timezones = app(TimezoneService::class);
            try {
                $data['drip_at'] = $timezones->resolveLocalWallTime($request->string('drip_local')->toString(), $timezones->getBusinessTimezone(), 'reject')->toIso8601String();
            } catch (DstGapException|DstFoldAmbiguityException $exception) {
                throw ValidationException::withMessages(['drip_local' => 'This local time is missing or repeated at a clock change. Choose an unambiguous time.']);
            }
        }
        if ($values['operation'] === 'access') {
            foreach (['starts_at', 'expires_at'] as $field) {
                if (! empty($data[$field])) {
                    $request->validate([$field => ['date_format:Y-m-d\\TH:i,Y-m-d\\TH:i:s']]);
                    $data[$field] = CarbonImmutable::parse($data[$field], 'UTC')->toIso8601String();
                }
            }
        }
        try {
            $this->studio->write($actor, $course, $values['operation'], $data, (int) $values['version']);
        } catch (HttpException $exception) {
            return $this->conflict($request, $course, $exception);
        }

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'Draft saved. Learners see these changes only after you publish the course.');
    }

    public function lifecycle(Request $request, Course $course): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'unpublished', 'archived'])], 'version' => ['required', 'integer', 'min:1']]);
        try {
            $this->studio->lifecycle($actor, $course, $values['status'], (int) $values['version']);
        } catch (HttpException $exception) {
            return $this->conflict($request, $course, $exception);
        }

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', match ($values['status']) {
            'published' => 'Course published. The selected section and lesson states are preserved.',
            'unpublished' => 'Course unpublished. Learner access is closed.',
            'archived' => 'Course archived. Its content and history are retained.', default => 'Course restored to Draft. Learner access stays closed.'
        });
    }

    public function duplicate(Request $request, Course $course): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        try {
            $copy = $this->studio->duplicate($actor, $course, (int) $values['version']);
        } catch (HttpException $exception) {
            return $this->conflict($request, $course, $exception);
        }

        return redirect()->route('admin.lms.courses.edit', $copy)->with('success', 'Draft copy created. Enrollment, grants and Student learning history were not copied.');
    }

    public function discard(Request $request, Course $course): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        try {
            $this->studio->discard($actor, $course, (int) $values['version']);
        } catch (HttpException $exception) {
            return $this->conflict($request, $course, $exception);
        }

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'Draft discarded. The retained live course is unchanged.');
    }

    public function upload(Request $request, Course $course): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['kind' => ['required', 'in:image,file'], 'file' => ['required', 'file'], 'version' => ['required', 'integer', 'min:1']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        try {
            $this->studio->upload($actor, $course, $file, $values['kind'], (int) $values['version']);
        } catch (HttpException $exception) {
            return $this->conflict($request, $course, $exception);
        }

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'Attachment uploaded once. Choose it in an Image or File block below.');
    }

    public function preview(Request $request, Course $course): Response
    {
        $actor = $this->actor($request);
        $graph = $this->studio->draft($actor, $course);
        $assetIds = $this->assetIds($graph);
        foreach ($graph['sections'] as &$section) {
            foreach ($section['lessons'] as &$lesson) {
                foreach ($lesson['blocks'] as &$block) {
                    if ($block['status'] !== 'ready') {
                        continue;
                    }
                    try {
                        $block = array_replace($block, $this->content->normalize($course, $this->content->input($block), $assetIds, $this->assetIds($graph, 'video_asset_id')));
                    } catch (ValidationException $exception) {
                        $block['preview_error'] = implode(' ', Arr::flatten($exception->errors()));
                    }
                } unset($block);
            } unset($lesson);
        } unset($section);
        $resourceIds = [];
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                foreach ($lesson['blocks'] as $block) {
                    if (! empty($block['resource_id'])) {
                        $resourceIds[] = $block['resource_id'];
                    }
                }
            }
        }

        return $this->view('admin.lms.courses.preview', ['title' => 'Author Preview', 'course' => $course, 'graph' => $graph,
            'resources' => Resource::query()->whereIn('id', $resourceIds)->get(['id', 'title'])->keyBy('id')]);
    }

    public function asset(Request $request, Course $course, LmsAsset $asset): Response
    {
        $actor = $this->actor($request);
        $graph = $this->studio->draft($actor, $course);
        abort_unless($asset->usableFor($course) && ((int) $asset->course_id === (int) $course->id || in_array((int) $asset->id, $this->assetIds($graph), true)), 404);

        return $this->files->openCourseAsset($asset);
    }

    public function resource(Request $request, Course $course, Resource $resource): Response
    {
        $graph = $this->studio->draft($this->actor($request), $course);
        $found = false;
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                foreach ($lesson['blocks'] as $block) {
                    if ($block['status'] === 'ready' && (int) ($block['resource_id'] ?? 0) === (int) $resource->id) {
                        $found = true;
                    }
                }
            }
        }
        abort_unless($found && $resource->isPublished(), 404);

        return $this->files->openResource($resource);
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);
        Gate::forUser($actor)->authorize('manage', Course::class);

        return $actor;
    }

    /** @param array<string,mixed> $graph
     * @return list<int> */
    private function assetIds(array $graph, string $field = 'asset_id'): array
    {
        $ids = [];
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                foreach ($lesson['blocks'] as $block) {
                    if (! empty($block[$field])) {
                        $ids[] = (int) $block[$field];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function conflict(Request $request, Course $course, HttpException $exception): RedirectResponse
    {
        if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
            throw $exception;
        }

        return redirect()->route('admin.lms.courses.edit', $course)->withErrors(['version' => $exception->getMessage()]);
    }

    /** @param array<string,mixed> $data */
    private function view(string $view, array $data): Response
    {
        return response()->view($view, $data, 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
