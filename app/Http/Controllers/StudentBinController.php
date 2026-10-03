<?php

namespace App\Http\Controllers;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentBinController extends Controller
{
    private function student(Request $request, ?Student $student): Student
    {
        $student = $request->routeIs('student.*') ? $request->attributes->get('student') : $student;
        abort_unless($student instanceof Student, 404);

        return $student;
    }

    private function authorizeBin(Request $request, Student $student, StudentBin $bin, string $ability): void
    {
        abort_unless((int) $bin->student_id === (int) $student->id, 404);
        $actor = $request->routeIs('student.*') ? $request->user('student') : $request->user('web');
        if ($request->routeIs('student.*') && ! Gate::forUser($actor)->allows('view', $bin)) {
            abort(404);
        }
        Gate::forUser($actor)->authorize($ability, $bin);
    }

    public function index(Request $request, ?Student $student = null): View
    {
        $student = $this->student($request, $student);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $portal = $request->routeIs('student.*');
        $query = StudentBin::query()->where('student_id', $student->id)->when($portal, fn ($bins) => $bins->where('student_visible', true));
        if (! empty($filters['q'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($bins) => $bins->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }

        return view('student.educational-bins', ['student' => $student, 'bins' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters, 'portal' => $portal, 'editing' => null]);
    }

    public function store(Request $request, AuditLogService $audit, ?Student $student = null): RedirectResponse
    {
        $student = $this->student($request, $student);
        $portal = $request->routeIs('student.*');
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:30000'], 'student_visible' => ['nullable', 'boolean']]);
        $actor = $portal ? $request->user('student') : $request->user('web');
        $bin = StudentBin::create([...$data, 'student_id' => $student->id, 'created_by_type' => $portal ? 'student' : 'staff', 'created_by_id' => $actor->id, 'student_visible' => $portal || $request->boolean('student_visible')]);
        $this->audit($request, $audit, 'student_bin_created', $bin);

        return $this->redirect($request, $student)->with('success', 'Educational note created.');
    }

    public function edit(Request $request, ?Student $student = null, ?StudentBin $bin = null): View
    {
        $student = $this->student($request, $student);
        abort_unless($bin instanceof StudentBin, 404);
        $this->authorizeBin($request, $student, $bin, 'update');

        return view('student.educational-bins', ['student' => $student, 'bins' => collect(), 'filters' => [], 'portal' => $request->routeIs('student.*'), 'editing' => $bin]);
    }

    public function update(Request $request, AuditLogService $audit, ?Student $student = null, ?StudentBin $bin = null): RedirectResponse
    {
        $student = $this->student($request, $student);
        abort_unless($bin instanceof StudentBin, 404);
        $this->authorizeBin($request, $student, $bin, 'update');
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:30000'], 'student_visible' => ['nullable', 'boolean']]);
        $bin->update([...$data, 'student_visible' => $request->routeIs('student.*') || $request->boolean('student_visible')]);
        $this->audit($request, $audit, 'student_bin_updated', $bin);

        return $this->redirect($request, $student)->with('success', 'Educational note updated.');
    }

    public function destroy(Request $request, AuditLogService $audit, ?Student $student = null, ?StudentBin $bin = null): RedirectResponse
    {
        $student = $this->student($request, $student);
        abort_unless($bin instanceof StudentBin, 404);
        $this->authorizeBin($request, $student, $bin, 'delete');
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        DB::transaction(function () use ($request, $audit, $bin): void {
            $this->audit($request, $audit, 'student_bin_deleted', $bin);
            $bin->delete();
        });

        return $this->redirect($request, $student)->with('success', 'Educational note removed. Deletion history retained.');
    }

    private function redirect(Request $request, Student $student): RedirectResponse
    {
        return $request->routeIs('student.*') ? redirect()->route('student.bins.index') : redirect()->route('admin.student-bins.index', $student);
    }

    private function audit(Request $request, AuditLogService $audit, string $action, StudentBin $bin): void
    {
        $metadata = ['student_id' => $bin->student_id, 'owner_type' => $bin->created_by_type, 'owner_id' => $bin->created_by_id];
        if ($request->routeIs('student.*')) {
            $audit->logStudent($bin->student_id, $action, StudentBin::class, $bin->id, null, $metadata);
        } else {
            $audit->log($action, StudentBin::class, $bin->id, null, $metadata);
        }
    }
}
