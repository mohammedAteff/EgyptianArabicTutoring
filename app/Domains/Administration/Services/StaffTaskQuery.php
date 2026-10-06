<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffTask;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffTaskQuery
{
    /** @return array<string, mixed> */
    public function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'owner' => ['nullable', Rule::in(['mine', 'all'])],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'undated'])],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')->whereNot('identity_status', 'merged')],
        ]);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<StaffTask>
     */
    public function query(Administrator $administrator, array $filters = []): Builder
    {
        $query = StaffTask::query()->with(['student', 'assignee']);
        if (empty($filters['student_id'])) {
            $query->where(fn (Builder $tasks) => $tasks->whereNull('student_id')->orWhereHas('student', fn (Builder $students) => $students->where('operational_status', 'active')));
        }
        if (! $administrator->isAdmin() || ($filters['owner'] ?? '') === 'mine') {
            $query->where('assignee_id', $administrator->id);
        }
        foreach (['status', 'priority', 'student_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['q'])) {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%');
        }
        $today = now(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
        switch ($filters['due'] ?? '') {
            case 'overdue':
                $query->whereDate('due_date', '<', $today)->whereIn('status', ['open', 'in_progress']);
                break;
            case 'today':
                $query->whereDate('due_date', $today);
                break;
            case 'upcoming':
                $query->whereDate('due_date', '>', $today)->whereIn('status', ['open', 'in_progress']);
                break;
            case 'undated':
                $query->whereNull('due_date');
                break;
        }

        return $query->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id');
    }
}
