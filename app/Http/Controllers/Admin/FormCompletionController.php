<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Forms\Services\FormCompletionReadModel;
use App\Domains\Students\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class FormCompletionController extends Controller
{
    public function index(Request $request, FormCompletionReadModel $forms): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'filter' => ['nullable', 'in:all,short_missing,long_draft,long_completed,mandatory_upcoming']]);
        $query = Student::tutoringRoster()->where('operational_status', 'active')->orderBy('name_normalized');
        if (filled($filters['q'] ?? null)) {
            $query->where('name_normalized', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($filters['q'])).'%');
        }
        $page = max(1, $request->integer('page', 1));
        $items = [];
        $total = 0;
        $query->withExists(['bookings as has_upcoming' => fn ($query) => $query->where('start_at_utc', '>', now('UTC'))->whereIn('status', ['confirmed', 'held', 'pending'])]);
        if (($filters['filter'] ?? 'all') === 'all') {
            $rows = $query->paginate(25)->withQueryString();
            $metadata = $forms->forStudents($rows->getCollection()->modelKeys(), includeIntake: true);
            $rows->setCollection($rows->getCollection()->map(fn (Student $student): array => ['student' => $student, 'forms' => $metadata->get($student->id, collect())]));

            return view('admin.forms.completion', compact('rows', 'filters'));
        }
        $query->chunk(100, function ($students) use ($forms, $filters, $page, &$items, &$total): void {
            $batch = $forms->forStudents($students->modelKeys(), includeIntake: true);
            foreach ($students as $student) {
                $metadata = $batch->get($student->id, collect());
                $short = $metadata->firstWhere('slug', 'short-form');
                $long = $metadata->firstWhere('slug', 'long-form');
                $matches = match ($filters['filter'] ?? 'all') {
                    'short_missing' => $short && $short['status'] !== 'submitted',
                    'long_draft' => ($long['status'] ?? null) === 'draft',
                    'long_completed' => ($long['status'] ?? null) === 'submitted',
                    'mandatory_upcoming' => $student->getAttribute('has_upcoming') && $metadata->contains(fn (array $form): bool => $form['mandatory'] && $form['status'] !== 'submitted'),
                    default => true,
                };
                if ($matches) {
                    if ($total >= ($page - 1) * 25 && $total < $page * 25) {
                        $items[] = ['student' => $student, 'forms' => $metadata];
                    }
                    $total++;
                }
            }
        });
        $rows = new LengthAwarePaginator($items, $total, 25, $page, ['path' => route('admin.forms.completion'), 'query' => $request->query()]);

        return view('admin.forms.completion', compact('rows', 'filters'));
    }
}
