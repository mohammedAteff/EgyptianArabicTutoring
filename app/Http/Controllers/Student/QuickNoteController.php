<?php

namespace App\Http\Controllers\Student;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentBookmark;
use App\Domains\Students\Services\StudentBookmarkService;
use App\Domains\Students\Services\TeachingRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuickNoteController extends Controller
{
    public function session(): JsonResponse
    {
        return response()->json(['authenticated' => true]);
    }

    public function store(Request $request, TeachingRecordService $records, AuditLogService $audit): JsonResponse
    {
        $data = $request->validate(['creation_id' => ['required', 'uuid'], 'body' => ['required', 'string', 'max:30000']]);
        $this->validateBody($data['body']);
        $student = $request->attributes->get('student');
        $bin = DB::transaction(function () use ($data, $student, $records, $audit): StudentBin {
            $records->lockStudent($student->id);
            $existing = StudentBin::withTrashed()->where('student_id', $student->id)->where('quick_note_key', $data['creation_id'])->first();
            if ($existing) {
                abort_if($existing->trashed(), 410);
                $this->authorizeNote($student, $existing);

                return $existing;
            }
            $lines = preg_split('/\R/u', $data['body']);
            $firstLine = collect($lines)->first(fn (string $line): bool => preg_match('/[^\s\p{Z}]/u', $line) === 1);
            $bin = new StudentBin([
                'student_id' => $student->id, 'created_by_type' => 'student', 'created_by_id' => $student->id,
                'student_visible' => true, 'title' => mb_substr(trim($firstLine), 0, 160), 'body' => $data['body'],
            ]);
            $bin->quick_note_key = $data['creation_id'];
            $bin->save();
            $this->audit($audit, 'student_bin_created', $bin);

            return $bin;
        }, 3);

        return $this->noteResponse($bin);
    }

    public function show(Request $request, StudentBin $bin): JsonResponse
    {
        $this->authorizeNote($request->attributes->get('student'), $bin);

        return $this->noteResponse($bin);
    }

    public function update(Request $request, StudentBin $bin, AuditLogService $audit): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:30000'], 'revision' => ['required', 'integer', 'min:1']]);
        $this->validateBody($data['body']);

        return DB::transaction(function () use ($request, $bin, $data, $audit): JsonResponse {
            $current = StudentBin::query()->lockForUpdate()->findOrFail($bin->id);
            $this->authorizeNote($request->attributes->get('student'), $current);
            if ($current->revision !== (int) $data['revision'] && $current->body !== $data['body']) {
                return response()->json(['message' => __('This note changed in another editor. Copy your text before loading the current version.'), 'conflict' => true], 409);
            }
            if ($current->body !== $data['body']) {
                $current->update(['body' => $data['body']]);
                $this->audit($audit, 'student_bin_updated', $current);
            }

            return $this->noteResponse($current);
        }, 3);
    }

    public function bookmark(Request $request, StudentBin $bin, StudentBookmarkService $bookmarks, TeachingRecordService $records): JsonResponse
    {
        $data = $request->validate(['bookmarked' => ['required', 'boolean']]);
        $student = $request->attributes->get('student');

        return DB::transaction(function () use ($student, $bin, $data, $bookmarks, $records): JsonResponse {
            $records->lockStudent($student->id);
            $current = StudentBin::query()->lockForUpdate()->findOrFail($bin->id);
            $this->authorizeNote($student, $current);
            abort_unless($bookmarks->target($student, 'note', $current->id) !== null, 404);
            $identity = ['student_id' => $student->id, 'kind' => 'note', 'target_id' => $current->id];
            if ($data['bookmarked']) {
                StudentBookmark::query()->firstOrCreate($identity);
            } else {
                StudentBookmark::query()->where($identity)->delete();
            }

            return $this->noteResponse($current);
        }, 3);
    }

    private function authorizeNote(Student $student, StudentBin $bin): void
    {
        abort_unless((int) $bin->student_id === (int) $student->id && Gate::forUser($student)->allows('view', $bin), 404);
        Gate::forUser($student)->authorize('update', $bin);
    }

    private function validateBody(string $body): void
    {
        if (preg_match('/[^\s\p{Z}]/u', $body) !== 1) {
            throw ValidationException::withMessages(['body' => __('Write something before saving. Your last saved note is unchanged.')]);
        }
    }

    private function noteResponse(StudentBin $bin): JsonResponse
    {
        return response()->json(['note' => [
            'id' => $bin->id, 'title' => $bin->title, 'body' => $bin->body, 'revision' => $bin->revision,
            'bookmarked' => StudentBookmark::query()->where('student_id', $bin->student_id)->where('kind', 'note')->where('target_id', $bin->id)->exists(),
        ]]);
    }

    private function audit(AuditLogService $audit, string $action, StudentBin $bin): void
    {
        $audit->logStudent($bin->student_id, $action, StudentBin::class, $bin->id, null, ['student_id' => $bin->student_id, 'owner_type' => $bin->created_by_type, 'owner_id' => $bin->created_by_id]);
    }
}
