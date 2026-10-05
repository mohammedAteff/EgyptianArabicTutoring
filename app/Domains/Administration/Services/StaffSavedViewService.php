<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffSavedView;
use App\Domains\Students\Services\StudentRecordsQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffSavedViewService
{
    public const ROUTES = ['students' => 'admin.students.index', 'staff_notes' => 'admin.staff-bins.index', 'tasks' => 'admin.tasks.index'];

    public const KEYS = [
        'students' => ['q', 'status', 'package', 'credits', 'expiry_from', 'expiry_to', 'timezone', 'session_status', 'joined_from', 'joined_to'],
        'staff_notes' => ['q', 'sort', 'owner', 'collection'],
        'tasks' => ['q', 'status', 'priority', 'owner', 'due', 'student_id'],
    ];

    /** @return array<string, mixed> */
    public function noteFilters(Request $request): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:255'], 'sort' => ['nullable', 'in:newest,oldest,title'], 'owner' => ['nullable', 'in:mine,all'], 'collection' => ['nullable', 'in:all,shared,mine,favorites']]);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validateFilters(string $section, array $filters): array
    {
        Validator::make(['filters' => $filters], ['filters' => ['array:'.implode(',', self::KEYS[$section])]])->validate();
        $request = Request::create('/', 'GET', $filters);
        $validated = match ($section) {
            'students' => app(StudentRecordsQuery::class)->filters($request),
            'tasks' => app(StaffTaskQuery::class)->filters($request),
            default => $this->noteFilters($request),
        };

        return array_filter($validated, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @return Collection<int, StaffSavedView> */
    public function forSection(Administrator $administrator, string $section): Collection
    {
        return StaffSavedView::query()->where('administrator_id', $administrator->id)->where('section', $section)->orderBy('name')->get();
    }

    public function section(Request $request): string
    {
        return $request->validate(['section' => ['required', Rule::in(array_keys(self::ROUTES))]])['section'];
    }
}
