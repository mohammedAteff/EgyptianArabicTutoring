<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Services\LmsVideoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseVideoController extends Controller
{
    public function external(Request $request, Course $course, LmsVideoService $videos): RedirectResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $videos->external($this->actor($request), $course, $request->only(['provider', 'url', 'label', 'request_key']), (int) $values['version']);

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'External video asset attached. Protection is controlled by that external provider.');
    }

    public function store(Request $request, Course $course, LmsVideoService $videos): JsonResponse
    {
        $values = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $asset = $videos->createUpload($this->actor($request), $course, $request->only(['label', 'request_key']), (int) $values['version']);

        return $this->json(['asset_id' => $asset->id, 'status' => $asset->status, 'authorization_url' => route('admin.lms.videos.upload', [$course, $asset]), 'status_url' => route('admin.lms.videos.status', [$course, $asset])]);
    }

    public function upload(Request $request, Course $course, VideoAsset $asset, LmsVideoService $videos): JsonResponse
    {
        return $this->json($videos->uploadAuthorization($this->actor($request), $course, $asset));
    }

    public function status(Request $request, Course $course, VideoAsset $asset, LmsVideoService $videos): JsonResponse
    {
        Gate::forUser($this->actor($request))->authorize('manage', $course);
        abort_unless((int) $asset->course_id === (int) $course->id, 404);
        $asset = $videos->reconcile($asset);

        return $this->json($asset->only(['id', 'label', 'status', 'duration_seconds', 'failure_code']) + ['referenced' => $videos->hasReferences($asset)]);
    }

    public function destroy(Request $request, Course $course, VideoAsset $asset, LmsVideoService $videos): RedirectResponse
    {
        $videos->deleteRemote($this->actor($request), $course, $asset);

        return redirect()->route('admin.lms.courses.edit', $course)->with('success', 'Unreferenced remote media deleted.');
    }

    private function actor(Request $request): Administrator
    {
        $actor = $request->user('web');
        abort_unless($actor instanceof Administrator, 403);

        return $actor;
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
