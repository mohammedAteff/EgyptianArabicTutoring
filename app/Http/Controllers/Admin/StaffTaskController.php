<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Administration\Services\StaffSavedViewService;
use App\Domains\Administration\Services\StaffTaskQuery;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffTaskController extends Controller
{
    public function index(Request $request, StaffTaskQuery $tasks, StaffSavedViewService $views): View
    {
        Gate::authorize('viewAny', StaffTask::class);
        $filters = $tasks->filters($request);

        return view('admin.staff-tasks', [
            'title' => 'Staff Tasks', 'tasks' => $tasks->query($request->user('web'), $filters)->paginate(20)->withQueryString(),
            'filters' => $filters, 'savedViews' => $views->forSection($request->user('web'), 'tasks'),
            'assignees' => Administrator::query()->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin', 'assistant'])->when(! $request->user('web')->isAdmin(), fn ($query) => $query->whereKey($request->user('web')->id))->orderBy('name')->get(['id', 'name']),
            'selectedStudent' => ! empty($filters['student_id']) ? Student::findOrFail($filters['student_id']) : null,
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('create', StaffTask::class);
        $data = $this->validated($request);
        if (! $request->user('web')->isAdmin()) {
            abort_unless((int) $data['assignee_id'] === (int) $request->user('web')->id, 403);
        }
        DB::transaction(function () use ($data, $request, $audit): void {
            if (! empty($data['student_id'])) {
                Student::query()->where('identity_status', '!=', 'merged')->lockForUpdate()->findOrFail($data['student_id']);
            }
            $task = StaffTask::create([...$data, 'created_by' => $request->user('web')->id, 'completed_at' => $data['status'] === 'completed' ? now('UTC') : null]);
            $audit->log('staff_task_created', StaffTask::class, $task->id, null, ['assignee_id' => $task->assignee_id, 'student_id' => $task->student_id, 'status' => $task->status]);
        });

        return redirect()->route('admin.tasks.index')->with('success', 'Staff task created.');
    }

    public function update(Request $request, StaffTask $task, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('update', $task);
        $data = $request->user('web')->isAdmin() ? $this->validated($request) : $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])]]);
        DB::transaction(function () use ($task, $data, $audit): void {
            $studentId = $data['student_id'] ?? $task->student_id;
            if ($studentId) {
                Student::query()->where('identity_status', '!=', 'merged')->lockForUpdate()->findOrFail($studentId);
            }
            $locked = StaffTask::query()->lockForUpdate()->findOrFail($task->id);
            Gate::authorize('update', $locked);
            $previous = $locked->status;
            $locked->update([...$data, 'completed_at' => $data['status'] === 'completed' ? ($locked->completed_at ?? now('UTC')) : null]);
            $audit->log('staff_task_updated', StaffTask::class, $locked->id, ['status' => $previous], ['status' => $locked->status, 'assignee_id' => $locked->assignee_id]);
        });

        return back()->with('success', 'Task updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:10000'],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')->whereNot('identity_status', 'merged')],
            'assignee_id' => ['required', 'integer', Rule::exists('administrators', 'id')->whereNull('deleted_at')->whereNull('suspended_at')->whereIn('role', ['super_admin', 'admin', 'assistant'])],
            'due_date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
        ]);
    }
}
