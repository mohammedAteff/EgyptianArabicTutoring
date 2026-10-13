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
        $items = [];
        foreach ($query->withExists(['bookings as has_upcoming' => fn ($query) => $query->where('start_at_utc', '>', now('UTC'))->whereIn('status', ['confirmed', 'held', 'pending'])])->lazy(100) as $student) {
            $metadata = $forms->forStudent($student);
            $short = $metadata->firstWhere('slug', 'short-form');
            $long = $metadata->firstWhere('slug', 'long-form');
            $matches = match ($filters['filter'] ?? 'all') {
                'short_missing' => $short && $short['status'] !== 'submitted',
                'long_draft' => ($long['status'] ?? null) === 'draft',
                'long_completed' => ($long['status'] ?? null) === 'submitted',
                'mandatory_upcoming' => $student->has_upcoming && $metadata->contains(fn (array $form): bool => $form['mandatory'] && $form['status'] !== 'submitted'),
                default => true,
            };
            if ($matches) {
                $items[] = ['student' => $student, 'forms' => $metadata];
            }
        }
        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator(array_slice($items, ($page - 1) * 25, 25), count($items), 25, $page, ['path' => route('admin.forms.completion'), 'query' => $request->query()]);

        return view('admin.forms.completion', compact('rows', 'filters'));
    }
}
