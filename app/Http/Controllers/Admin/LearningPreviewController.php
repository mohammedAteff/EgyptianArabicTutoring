<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Services\LmsPreviewService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LearningPreviewController extends Controller
{
    public function __construct(private LmsPreviewService $preview) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        $v = $request->validate(['student_id' => ['nullable', 'integer', 'min:1'], 'request_key' => ['required', 'uuid']]);
        $id = $this->preview->begin($request, $course, isset($v['student_id']) ? (int) $v['student_id'] : null, $v['request_key']);

        return to_route('admin.lms.preview.show', $id);
    }

    public function show(Request $request, string $preview, ?int $lesson = null): Response
    {
        return response()->view('admin.lms.preview.show', $this->preview->data($request, $preview, $lesson), 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'strict-origin', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    public function destroy(Request $request, string $preview): RedirectResponse
    {
        $this->preview->end($request, $preview);

        return to_route('admin.lms.courses.index')->with('success', 'Learner preview ended.');
    }

    public function material(Request $request, string $preview, int $lesson, int $block, LessonMaterialService $files): Response
    {
        [$course,$current,$target] = $this->preview->target($request, $preview, $lesson, $block);
        if (in_array($target->kind, ['resource', 'file'], true) && $target->resource) {
            return $files->openResource($target->resource);
        }
        abort_unless(in_array($target->kind, ['image', 'file'], true) && $target->asset, 404);

        return $files->openCourseAsset($target->asset);
    }

    public function video(Request $request, string $preview, int $lesson, int $block): Response
    {
        return response()->json($this->preview->authorizeVideo($request, $preview, $lesson, $block), 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function renew(Request $request, string $preview, int $lesson, int $block): Response
    {
        return response()->json($this->preview->authorizeVideo($request, $preview, $lesson, $block, true), 200,
            ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function close(Request $request, string $preview, int $block): Response
    {
        $this->preview->closeVideo($request, $preview, $block);

        return response()->noContent(204, ['Cache-Control' => 'private, no-store']);
    }
}
