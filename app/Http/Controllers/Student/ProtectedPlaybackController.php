<?php

namespace App\Http\Controllers\Student;

use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Services\ProtectedPlaybackService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectedPlaybackController extends Controller
{
    public function authorize(Request $request, Course $course, Lesson $lesson, LessonBlock $block, ProtectedPlaybackService $playback): JsonResponse
    {
        return $this->json($playback->authorize($request, $course, $lesson, $block));
    }

    public function renew(Request $request, Course $course, Lesson $lesson, LessonBlock $block, int $lease, ProtectedPlaybackService $playback): JsonResponse
    {
        return $this->json($playback->authorize($request, $course, $lesson, $block, $lease));
    }

    public function close(Request $request, int $lease, ProtectedPlaybackService $playback): JsonResponse
    {
        $playback->close($request, $lease);

        return $this->json(['closed' => true]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): JsonResponse
    {
        return response()->json($data, 200, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }
}
