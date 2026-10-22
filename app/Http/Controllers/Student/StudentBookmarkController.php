<?php

namespace App\Http\Controllers\Student;

use App\Domains\Students\Models\StudentBookmark;
use App\Domains\Students\Services\StudentBookmarkService;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StudentBookmarkController extends Controller
{
    public function index(Request $request, StudentBookmarkService $bookmarks): Response
    {
        $student = $request->attributes->get('student');
        $items = StudentBookmark::query()->where('student_id', $student->id)->orderByDesc('id')->get()->map(function (StudentBookmark $bookmark) use ($student, $bookmarks): ?array {
            $target = $bookmarks->target($student, $bookmark->kind, $bookmark->target_id);

            return $target ? ['bookmark' => $bookmark, 'title' => $target->title ?? $target->getRelationValue('resource')?->title, 'url' => $bookmarks->url($bookmark->kind, $target)] : null;
        })->filter();

        return response()->view('student.bookmarks', compact('items'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, StudentBookmarkService $bookmarks, TeachingRecordService $records): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:resource,note,homework'], 'target_id' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $data, $bookmarks, $records): void {
            $student = $records->lockStudent($request->attributes->get('student')->id);
            abort_unless($bookmarks->target($student, $data['kind'], $data['target_id']) !== null, 404);
            StudentBookmark::query()->firstOrCreate(['student_id' => $student->id] + $data);
        }, 3);

        return back()->with('success', 'Bookmark saved.');
    }

    public function destroy(Request $request, int $bookmark): RedirectResponse
    {
        StudentBookmark::query()->where('student_id', $request->attributes->get('student')->id)->findOrFail($bookmark)->delete();

        return back()->with('success', 'Bookmark removed.');
    }
}
