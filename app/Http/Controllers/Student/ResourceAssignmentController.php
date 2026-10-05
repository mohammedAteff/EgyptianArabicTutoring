<?php

namespace App\Http\Controllers\Student;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Services\StudentTeachingReadModel;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResourceAssignmentController extends Controller
{
    public function open(Request $request, int $assignment, StudentTeachingReadModel $read, LessonMaterialService $materials): Response
    {
        $item = $read->shared(ResourceAssignment::query(), $request->attributes->get('student'))->with('resource')->findOrFail($assignment);
        abort_unless($item->resource && $item->resource->isPublished(), 404);

        return $materials->openResource($item->resource);
    }

    public function update(Request $request, int $assignment, TeachingRecordService $records, StudentTeachingReadModel $read): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment, $records, $read): void {
            $student = $records->lockStudent($request->attributes->get('student')->id);
            $item = $read->shared(ResourceAssignment::query(), $student)->whereHas('resource', fn ($query) => $query->published())->lockForUpdate()->findOrFail($assignment);
            $item->update(['reviewed_at' => $item->reviewed_at ?? now('UTC')]);
        }, 3);

        return to_route('student.teaching.index')->with('success', 'Resource marked as reviewed.');
    }
}
